<?php

namespace App\Enums;

/** Phase 7 — reusable proposal content building blocks. */
enum TemplateCategory: string
{
    case CompanyProfile = 'company_profile';
    case TechnicalNarrative = 'technical_narrative';
    case QaQc = 'qa_qc';
    case Warranty = 'warranty';
    case TrainingPlan = 'training_plan';
    case InstallationPlan = 'installation_plan';
    case SupportPlan = 'support_plan';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CompanyProfile => 'Company Profile',
            self::TechnicalNarrative => 'Technical Narrative',
            self::QaQc => 'QA/QC Statement',
            self::Warranty => 'Warranty Statement',
            self::TrainingPlan => 'Training Plan',
            self::InstallationPlan => 'Installation Plan',
            self::SupportPlan => 'Support Plan',
            self::Other => 'Other',
        };
    }
}
