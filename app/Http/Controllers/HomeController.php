<?php

namespace App\Http\Controllers;

use App\Models\Movie;

class HomeController extends Controller
{
    public function index()
    {
        $featured = Movie::nowShowing()->where('is_featured', true)->first()
            ?? Movie::nowShowing()->first();

        return view('home', [
            'featured' => $featured,
            'nowShowing' => Movie::nowShowing()->orderBy('title')->get(),
            'comingSoon' => Movie::comingSoon()->orderBy('release_date')->get(),
        ]);
    }
}
