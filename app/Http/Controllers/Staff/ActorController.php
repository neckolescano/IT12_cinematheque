<?php

namespace App\Http\Controllers\Staff;

use App\Models\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActorController extends PersonController
{
    protected function modelClass(): string
    {
        return Actor::class;
    }

    protected function routePrefix(): string
    {
        return 'staff.actors';
    }

    protected function label(): string
    {
        return 'Actor';
    }

    public function edit(Actor $actor): View
    {
        return $this->editPerson($actor);
    }

    public function update(Request $request, Actor $actor): RedirectResponse
    {
        return $this->updatePerson($request, $actor);
    }

    public function destroy(Actor $actor): RedirectResponse
    {
        return $this->destroyPerson($actor);
    }
}
