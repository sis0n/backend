<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class BookService
{
    public function getCatalog($filters)
    {
        $query = DB::table('books')
            ->select(
                'book_id',
                'accession_number',
                'call_number',
                'title',
                'author',
                'book_publisher',
                'year',
                'book_edition',
                'description',
                'book_isbn',
                'subject',
                'availability',
                'quantity',
                'cover'
            )
            ->where('is_archived', 0)
            ->whereNull('deleted_at');

        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', $searchTerm)
                    ->orWhere('author', 'like', $searchTerm)
                    ->orWhere('book_isbn', 'like', $searchTerm);
            });
        }

        if (!empty($filters['status'])) {
            $query->where('availability', $filters['status']);
        }

        switch ($filters['sort'] ?? 'newest') {
            case 'az':
                $query->orderBy('title', 'asc');
                break;
            case 'za':
                $query->orderBy('title', 'desc');
                break;
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            case 'newest':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        return $query->paginate(30);
    }
}
