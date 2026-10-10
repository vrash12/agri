<?php

namespace App\Policies;

use App\Models\User;

final class OfficeAssistantPolicy
{
    public function use(User $user): bool
    {
        return ! $user->isVisitor() && ! $user->isGisEvaluator()
            && $user->hasUsableScope()
            && $user->hasAnyRole([
                User::ROLE_SYSTEM_OWNER, User::ROLE_REGIONAL_HEAD,
                User::ROLE_SUPER_ADMIN, User::ROLE_PROVINCIAL_STAFF,
                User::ROLE_PROVINCIAL_VET, User::ROLE_MUNICIPAL_HEAD,
                User::ROLE_MUNICIPAL_STAFF,
            ]);
    }
}
