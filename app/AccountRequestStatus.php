<?php

namespace App;

enum AccountRequestStatus: string
{
    case PendingEmailConfirmation = 'pending_email_confirmation';
    case PendingDeveloperApproval = 'pending_developer_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';
}
