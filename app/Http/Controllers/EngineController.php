<?php

namespace App\Http\Controllers;

use App\Models\Engine;
use Illuminate\Http\Request;

class EngineController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $query = Engine::query()
            ->where('is_archived', false)
            ->orderBy('brand')
            ->orderBy('code');

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('brand', 'like', "%{$q}%")
                  ->orWhere('code', 'like', "%{$q}%")
                  ->orWhere('description', 'like', "%{$q}%");
            });
        }

        $engines = $query->paginate(50)->withQueryString();

        return view('engines.index', compact('engines', 'q'));
    }

    public function create()
    {
        return view('engines.form', ['engine' => null]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        Engine::create(array_merge($data, [
            'company_id'  => auth()->user()->company_id,
            'is_archived' => false,
        ]));
        return redirect()->route('engines.index')->with('status', 'Engine added.');
    }

    public function edit(string $id)
    {
        $engine = Engine::findOrFail($id);
        return view('engines.form', compact('engine'));
    }

    public function update(string $id, Request $request)
    {
        $engine = Engine::findOrFail($id);
        $engine->update($this->validated($request));
        return redirect()->route('engines.index')->with('status', 'Engine updated.');
    }

    public function destroy(string $id)
    {
        $engine = Engine::findOrFail($id);
        $engine->delete();
        return back()->with('status', 'Engine removed.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'brand'       => 'required|string|max:100',
            'code'        => 'required|string|max:120',
            'horsepower'  => 'nullable|numeric|min:0',
            'fuel'        => 'nullable|in:petrol,diesel,electric,unknown',
            'description' => 'nullable|string|max:500',
            'cost'        => 'nullable|numeric|min:0',
            'price'       => 'required|numeric|min:0',
            'vat_rate'    => 'nullable|numeric|min:0|max:100',
            'currency'    => 'nullable|in:EUR,USD',
        ]);
    }
}
