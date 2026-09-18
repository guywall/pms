<?php

namespace App\Enums;

enum ArtworkStatus: string
{
    case Draft = 'draft';
    case InternalReview = 'internal_review';
    case AwaitingCustomer = 'awaiting_customer';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Superseded = 'superseded';
}
