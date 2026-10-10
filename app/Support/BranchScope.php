<?php

namespace App\Support;

use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Which branches a page of online orders covers.
 *
 * The owner sees every branch of the organization together, and can narrow
 * the page to one of them (?branch=<id>). A staff member only ever sees the
 * branch they work in, whatever the address says.
 */
class BranchScope
{
    /** @param  Collection<int, Pharmacy>  $branches  every branch this person may see */
    private function __construct(public Collection $branches, public ?Pharmacy $selected)
    {
    }

    public static function for(User $user, Request $request): self
    {
        if ($user->role !== 'vendor') {
            $branch = Pharmacy::resolveForUser($user);

            return new self(collect($branch ? [$branch] : []), $branch);
        }

        $branches = Pharmacy::branchesOf($user)->get();

        return new self($branches, $branches->count() === 1 ? $branches->first() : $branches->firstWhere('id', (int) $request->query('branch')));
    }

    /** Ids of the branches being shown: the chosen one, or all of them. */
    public function ids(): array
    {
        return $this->selected ? [$this->selected->id] : $this->branches->pluck('id')->all();
    }

    /** True when several branches are on the page together, so each order must say which branch it is for. */
    public function showsSeveral(): bool
    {
        return ! $this->selected && $this->branches->count() > 1;
    }

    /** True when the person can switch between branches on the page. */
    public function canChoose(): bool
    {
        return $this->branches->count() > 1;
    }

    public function label(): string
    {
        return $this->selected?->name ?? 'all your branches';
    }

    /** The ?branch= value that keeps the current choice in a link, or null for "all". */
    public function param(): ?int
    {
        return $this->canChoose() ? $this->selected?->id : null;
    }
}
