<?php

namespace App\Helpers;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

class CollectionHelper
{
    /**
     * Paginate a standard Laravel Collection.
     *
     * @param Collection $results
     * @param int $showPerPage
     * @param string $pageName
     * @return LengthAwarePaginator
     */
    public static function paginate(Collection $results, $showPerPage = 15, $pageName = 'page')
    {
        $pageNumber = Paginator::resolveCurrentPage($pageName);
        $totalPageNumber = $results->count();

        $results = $results->forPage($pageNumber, $showPerPage)->values();

        return new LengthAwarePaginator(
            $results,
            $totalPageNumber,
            $showPerPage,
            $pageNumber,
            [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ]
        );
    }

    /**
     * Search within a collection across multiple attributes.
     *
     * @param Collection $collection
     * @param string|null $searchTerm
     * @param array $attributes
     * @return Collection
     */
    public static function search(Collection $collection, $searchTerm, array $attributes = [])
    {
        $searchTerm = trim((string) $searchTerm);
        if ($searchTerm === '') {
            return $collection;
        }

        $searchTerm = mb_strtolower($searchTerm);

        return $collection->filter(function ($item) use ($searchTerm, $attributes) {
            if (empty($attributes)) {
                $haystack = self::flattenSearchableValue($item);
                return str_contains(mb_strtolower($haystack), $searchTerm);
            }

            foreach ($attributes as $attribute) {
                $value = data_get($item, $attribute, '');
                if (str_contains(mb_strtolower(self::flattenSearchableValue($value)), $searchTerm)) {
                    return true;
                }
            }
            return false;
        });
    }

    private static function flattenSearchableValue($value): string
    {
        if ($value instanceof Collection) {
            $value = $value->all();
        }

        if (is_array($value) || is_object($value)) {
            return collect((array) $value)
                ->map(fn ($item) => self::flattenSearchableValue($item))
                ->implode(' ');
        }

        return (string) $value;
    }
}
