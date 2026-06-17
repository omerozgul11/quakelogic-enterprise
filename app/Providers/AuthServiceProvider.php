<?php

namespace App\Providers;

use App\Models\Agency;
use App\Models\Commission;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Crm\Invoice;
use App\Models\Crm\Lead;
use App\Models\Crm\Project;
use App\Models\Opportunity;
use App\Models\ProposalMailing;
use App\Models\ProposalSubmission;
use App\Policies\AgencyPolicy;
use App\Policies\CommissionPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\ContactPolicy;
use App\Policies\Crm\InvoicePolicy;
use App\Policies\Crm\LeadPolicy;
use App\Policies\Crm\ProjectPolicy;
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
        Lead::class => LeadPolicy::class,
        Project::class => ProjectPolicy::class,
        Invoice::class => InvoicePolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
