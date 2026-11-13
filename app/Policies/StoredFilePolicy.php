<?php

namespace App\Policies;

use App\Models\StoredFile;
use App\Models\User;
use App\Support\FileAccessRegistry;

class StoredFilePolicy
{
    public function __construct(private readonly FileAccessRegistry $registry) {}

    public function view(User $user, StoredFile $file): bool
    {
        if ($file->uploaded_by !== null && $file->uploaded_by === $user->getKey()) {
            return true;
        }

        return $this->registry->allows($user, $file);
    }
}
