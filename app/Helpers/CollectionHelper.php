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
        if (empty($searchTerm)) {
            return $collection;
        }

        $searchTerm = strtolower($searchTerm);

        return $collection->filter(function ($item) use ($searchTerm, $attributes) {
            foreach ($attributes as $attribute) {
                $value = is_array($item) ? ($item[$attribute] ?? '') : ($item->$attribute ?? '');
                if (str_contains(strtolower((string)$value), $searchTerm)) {
                    return true;
                }
            }
            return false;
        });
    }
}
