<?php

namespace App\Enums;

/**
 * Mirrors the seeded `roles` table slugs. A user may hold multiple roles
 * (e.g. teacher + parent) via the role_user pivot, so this enum is used for
 * role slugs/checks, not as a single column on users.
 */
enum UserRole: string
{
    case Student = 'student';
    case Teacher = 'teacher';
    case Parent = 'parent';
    case Admin = 'admin';
}
