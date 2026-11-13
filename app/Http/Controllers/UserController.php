<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Services\UserManagement\UserService;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(
        protected UserRepository $userRepository,
        protected RoleRepository $roleRepository,
        protected UserService $userService
    ) {}

    /**
     * Samakan format email/nomor HP SEBELUM validasi unik, supaya
     * "0812..." dan "+62812..." terdeteksi sebagai nomor yang sama.
     */
    private function normalizeIdentity(Request $request): void
    {
        $request->merge([
            'email' => filled($request->input('email')) ? Str::lower(trim((string) $request->input('email'))) : null,
            'phone' => Phone::normalize($request->input('phone')),
        ]);
    }

    /**
     * Display a paginated list of users.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $filters = $request->only(['search', 'role', 'status']);
        $perPage = $request->integer('per_page', 10);
        $perPage = in_array($perPage, [5, 10, 25, 50, 100]) ? $perPage : 10;

        $allowedSort = ['name', 'email', 'phone', 'status', 'created_at'];
        $sort = $request->input('sort', 'created_at');
        $direction = $request->input('direction', 'desc');

        if (! in_array($sort, $allowedSort)) {
            $sort = 'created_at';
        }
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        $users = $this->userRepository->paginate(array_merge($filters, [
            'per_page' => $perPage,
            'sort' => $sort,
            'direction' => $direction,
        ]));
        $roles = $this->roleRepository->all();

        return view('users.index', compact('users', 'roles'));
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(): View
    {
        Gate::authorize('create', User::class);

        $roles = $this->roleRepository->all();

        return view('users.create', compact('roles'));
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', User::class);

        $this->normalizeIdentity($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Warga boleh tanpa email, asal punya nomor HP untuk login.
            'email' => ['nullable', 'required_without:phone', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:20', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
            'roles' => ['required', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $user = $this->userService->createUser($validated);

        return redirect()->route('users.index')
            ->with('success', "User \"{$user->name}\" created successfully.");
    }

    /**
     * Show the form for editing a user.
     */
    public function edit(User $user): View
    {
        Gate::authorize('view', $user);

        $roles = $this->roleRepository->all();
        $user->load('roles');

        return view('users.edit', compact('user', 'roles'));
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $this->normalizeIdentity($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'required_without:phone', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
            'roles' => ['required', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $this->userService->updateUser($user, $validated);

        return redirect()->route('users.index')
            ->with('success', "User \"{$user->name}\" updated successfully.");
    }

    /**
     * Delete the specified user.
     */
    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $name = $user->name;
        try {
            $this->userService->deleteUser($user);

            return redirect()->route('users.index')
                ->with('success', "User \"{$name}\" deleted successfully.");
        } catch (\Exception $e) {
            return redirect()->route('users.index')
                ->with('error', $e->getMessage());
        }
    }
}
