<?php

namespace App\Policies;

use App\Models\ReportTemplate;
use App\Models\User;

class ReportTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ReportTemplate $template): bool
    {
        return $user->id === $template->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ReportTemplate $template): bool
    {
        return $user->id === $template->user_id;
    }

    public function delete(User $user, ReportTemplate $template): bool
    {
        return $user->id === $template->user_id;
    }
}
