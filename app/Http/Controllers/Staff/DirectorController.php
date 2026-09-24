<?php

namespace App\Http\Controllers\Staff;

use App\Models\Director;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DirectorController extends PersonController
{
    protected function modelClass(): string
    {
        return Director::class;
    }

    protected function routePrefix(): string
    {
        return 'staff.directors';
    }

    protected function label(): string
    {
        return 'Director';
    }

    public function edit(Director $director): View
    {
        return $this->editPerson($director);
    }

    public function update(Request $request, Director $director): RedirectResponse
    {
        return $this->updatePerson($request, $director);
    }

    public function destroy(Director $director): RedirectResponse
    {
        return $this->destroyPerson($director);
    }
}
