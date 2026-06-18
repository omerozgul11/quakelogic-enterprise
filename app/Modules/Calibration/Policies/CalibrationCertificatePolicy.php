<?php

namespace App\Modules\Calibration\Policies;

use App\Models\User;
use App\Modules\Calibration\Models\CalibrationCertificate;

class CalibrationCertificatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view calibration');
    }

    public function view(User $user, CalibrationCertificate $cert): bool
    {
        return $this->sameOrg($user, $cert) && $user->can('view calibration');
    }

    public function create(User $user): bool
    {
        return $user->can('manage calibration');
    }

    public function update(User $user, CalibrationCertificate $cert): bool
    {
        return $this->sameOrg($user, $cert) && $user->can('manage calibration');
    }

    public function delete(User $user, CalibrationCertificate $cert): bool
    {
        return $this->sameOrg($user, $cert) && $user->can('manage calibration');
    }

    private function sameOrg(User $user, CalibrationCertificate $cert): bool
    {
        return $user->organization_id === $cert->organization_id;
    }
}
