<?php

namespace App\Services;

use App\Enums\ArtworkStatus;
use App\Models\ArtworkVersion;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ArtworkService
{
    public function __construct(
        private NotificationService $notifications,
        private OrderStageService $stages,
    ) {}

    public function submitForCustomerApproval(ArtworkVersion $version, User $actor): void
    {
        $this->assertDraft($version);
        $version->update(['status' => ArtworkStatus::AwaitingCustomer]);

        activity('artwork')->performedOn($version->order)->causedBy($actor)
            ->withProperties(['version' => $version->version_number])
            ->log("Artwork v{$version->version_number} sent to customer for approval");

        $this->notifications->artworkAwaitingApproval($version);
    }

    public function approve(ArtworkVersion $version, User $actor): void
    {
        if ($version->status === ArtworkStatus::Approved) {
            throw new InvalidArgumentException('This version is already approved.');
        }

        DB::transaction(function () use ($version, $actor) {
            $order = $version->order;

            // Only one final version per order
            $order->artworkVersions()
                ->where('is_final', true)
                ->where('id', '!=', $version->id)
                ->update(['is_final' => false, 'status' => ArtworkStatus::Superseded]);

            $version->update([
                'status' => ArtworkStatus::Approved,
                'is_final' => true,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            activity('artwork')->performedOn($order)->causedBy($actor)
                ->withProperties(['version' => $version->version_number])
                ->log("Artwork v{$version->version_number} approved and set as final");

            // Orders that only existed for artwork approval can now move on
            if ($order->stage === \App\Enums\StageKey::Artwork) {
                $this->stages->moveTo($order, \App\Enums\StageKey::NewOrder, $actor, 'Artwork approved');
            }
        });

        $this->notifications->artworkApproved($version);
    }

    public function reject(ArtworkVersion $version, User $actor, string $reason): void
    {
        $version->update(['status' => ArtworkStatus::Rejected]);

        activity('artwork')->performedOn($version->order)->causedBy($actor)
            ->withProperties(['version' => $version->version_number, 'reason' => $reason])
            ->log("Artwork v{$version->version_number} rejected: {$reason}");

        $this->notifications->artworkRejected($version, $reason);
    }

    public function newVersionFrom(Order $order, ArtworkVersion $source, User $actor): ArtworkVersion
    {
        $version = $order->artworkVersions()->create([
            'version_number' => $this->nextVersionNumber($order),
            'status' => ArtworkStatus::Draft,
            'is_final' => false,
            'created_by' => $actor->id,
            'notes' => "Revision of v{$source->version_number}".($source->notes ? ": {$source->notes}" : ''),
        ]);

        // Copy files from the previous version as a starting point
        foreach ($source->getMedia('artwork') as $media) {
            $media->copy($version, 'artwork');
        }

        return $version;
    }

    private function nextVersionNumber(Order $order): int
    {
        return ((int) $order->artworkVersions()->max('version_number')) + 1;
    }

    private function assertDraft(ArtworkVersion $version): void
    {
        if ($version->status !== ArtworkStatus::Draft) {
            throw new InvalidArgumentException('Only draft versions can be submitted for approval.');
        }
    }
}
