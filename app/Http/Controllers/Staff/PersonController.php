<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Shared CRUD for the two "person" catalog tables (actors, directors),
 * which have identical columns. Concrete controllers only set the model and labels.
 */
abstract class PersonController extends Controller
{
    /** @return class-string<Model> */
    abstract protected function modelClass(): string;

    abstract protected function routePrefix(): string;

    abstract protected function label(): string;

    public function index(): View
    {
        $class = $this->modelClass();
        Gate::authorize('viewAny', $class);

        return view('staff.people.index', [
            'people' => $class::withCount('movies')->orderBy('last_name')->orderBy('first_name')->paginate(50),
            'routePrefix' => $this->routePrefix(),
            'label' => $this->label(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $class = $this->modelClass();
        Gate::authorize('create', $class);

        $class::create($this->validated($request));

        return redirect()->route($this->routePrefix().'.index')->with('status', $this->label().' added.');
    }

    protected function editPerson(Model $person): View
    {
        Gate::authorize('update', $person);

        return view('staff.people.edit', [
            'person' => $person,
            'routePrefix' => $this->routePrefix(),
            'label' => $this->label(),
        ]);
    }

    protected function updatePerson(Request $request, Model $person): RedirectResponse
    {
        Gate::authorize('update', $person);

        $person->update($this->validated($request));

        return redirect()->route($this->routePrefix().'.index')->with('status', $this->label().' updated.');
    }

    protected function destroyPerson(Model $person): RedirectResponse
    {
        Gate::authorize('delete', $person);

        $person->delete(); // pivot rows cascade

        return redirect()->route($this->routePrefix().'.index')->with('status', $this->label().' deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:50'],
            'middle_name' => ['nullable', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
        ]);
    }
}
