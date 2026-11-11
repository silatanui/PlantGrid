<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TableSort
{
    public function __construct(
        public readonly string $column,
        public readonly string $direction,
    ) {
    }

    /**
     * @param  array<int, string>  $allowed
     */
    public static function from(
        Request $request,
        array $allowed,
        string $default,
        string $defaultDirection = 'asc',
    ): self {
        $column = in_array($request->input('sort'), $allowed, true)
            ? (string) $request->input('sort')
            : $default;

        if ($request->filled('direction')) {
            $direction = $request->input('direction') === 'desc' ? 'desc' : 'asc';
        } else {
            $direction = $defaultDirection === 'desc' ? 'desc' : 'asc';
        }

        return new self($column, $direction);
    }

    public function apply(Builder $query): Builder
    {
        return $query->orderBy($this->column, $this->direction);
    }

    public function is(string $column): bool
    {
        return $this->column === $column;
    }

    /**
     * @return array{sort: string, direction: string}
     */
    public function toArray(): array
    {
        return [
            'sort' => $this->column,
            'direction' => $this->direction,
        ];
    }
}
