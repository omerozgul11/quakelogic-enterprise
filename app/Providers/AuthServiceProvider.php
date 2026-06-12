<?php

namespace App\Providers;

use App\Models\Agency;
use App\Models\Commission;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\ProposalMailing;
use App\Models\ProposalSubmission;
use App\Policies\AgencyPolicy;
use App\Policies\CommissionPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\ContactPolicy;
use App\Policies\OpportunityPolicy;
use App\Policies\ProposalMailingPolicy;
use App\Policies\ProposalSubmissionPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Opportunity::class => OpportunityPolicy::class,
        ProposalSubmission::class => ProposalSubmissionPolicy::class,
        Agency::class => AgencyPolicy::class,
        Company::class => CompanyPolicy::class,
        Contact::class => ContactPolicy::class,
        Commission::class => CommissionPolicy::class,
        ProposalMailing::class => ProposalMailingPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
