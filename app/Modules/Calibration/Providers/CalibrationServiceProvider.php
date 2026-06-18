<?php

namespace App\Modules\Calibration\Providers;

use App\Modules\Calibration\Models\CalibrationCertificate;
use App\Modules\Calibration\Policies\CalibrationCertificatePolicy;
use App\Modules\ModuleServiceProvider;

class CalibrationServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        return dirname(__DIR__);
    }

    /** @return array<class-string,class-string> */
    protected function policies(): array
    {
        return [
            CalibrationCertificate::class => CalibrationCertificatePolicy::class,
        ];
    }
}
