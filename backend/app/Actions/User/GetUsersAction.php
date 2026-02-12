<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class GetUsersAction
{
    /**
     * @param User $authUser
     * @param array{
     *   per_page?: int,
     *   page?: int,
     *   search?: string,
     *   role_id?: int
     * } $filters
     * @return \Illuminate\Pagination\LengthAwarePaginator<int, User>
     */
    public function execute(User $authUser, array $filters): \Illuminate\Pagination\LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 10);
        $page = (int) ($filters['page'] ?? 1);

        $query = User::where('company_id', $authUser->company_id)
            ->with('role');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                // Use ILIKE for case-insensitive search in PostgreSQL
                $q->where('name', 'ILIKE', '%' . $search . '%')
                    ->orWhere('email', 'ILIKE', '%' . $search . '%');
            });
        }

        if (!empty($filters['role_id'])) {
            $query->where('role_id', $filters['role_id']);
        }

        /** @var \Illuminate\Pagination\LengthAwarePaginator<int, User> $paginator */
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        // Hide sensitive data
        $paginator->getCollection()->each(function (User $user) {
            $user->makeHidden(['password']);
        });

        return $paginator;
    }
}
