<?php

namespace App\Policies;

use App\Models\Shift;
use App\Models\User;

class ShiftPolicy
{
    public function view(User $u, Shift $s): bool
    {
        return $u->isSuper() || ($u->department_id === $s->department_id && (! $u->isStaff() || $s->created_by === $u->id));
    }

    public function update(User $u, Shift $s): bool
    {
        return $this->view($u, $s) && (! $u->isStaff() || in_array($s->status, ['draft', 'counting']) && $s->created_by === $u->id);
    }

    public function approve(User $u, Shift $s): bool
    {
        return ! $u->isStaff() && $this->view($u, $s);
    }

    public function delete(User $u, Shift $s): bool
    {
        return ! $u->isStaff() && $this->view($u, $s) && ! in_array($s->status, ['closed', 'approved']);
    }
}
