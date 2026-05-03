<?php

namespace App\Http\Controllers;

use App\Services\EmailTemplateService;
use Illuminate\Http\Request;

class EmailTemplateController extends Controller
{
    public function __construct(private EmailTemplateService $service)
    {
    }

    /**
     * Table view — one row per template (quote, order confirmation, follow-up).
     */
    public function index()
    {
        $company = auth()->user()->company;
        if (! $company) abort(403, 'No company on this account.');

        $templates = $this->service->getAll($company);

        return view('email-templates.index', [
            'templates' => $templates,
            'meta'      => EmailTemplateService::META,
        ]);
    }

    public function edit(string $type)
    {
        $company = auth()->user()->company;
        if (! $company) abort(403, 'No company on this account.');

        if (! in_array($type, EmailTemplateService::TYPES, true)) {
            abort(404);
        }

        $template = $this->service->getOrCreate($company, $type);

        return view('email-templates.edit', [
            'template'  => $template,
            'type'      => $type,
            'meta'      => EmailTemplateService::META[$type],
            'variables' => EmailTemplateService::availableVariables(),
            'sample'    => $this->service->sampleVariables($company),
        ]);
    }

    public function update(string $type, Request $request)
    {
        if (! in_array($type, EmailTemplateService::TYPES, true)) {
            abort(404);
        }

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:300'],
            'body'    => ['required', 'string', 'max:50000'],
        ]);

        $company  = auth()->user()->company;
        $template = $this->service->getOrCreate($company, $type);
        $template->update($validated);

        return redirect()
            ->route('email-templates.edit', $type)
            ->with('status', 'Template saved.');
    }

    public function reset(string $type)
    {
        if (! in_array($type, EmailTemplateService::TYPES, true)) {
            abort(404);
        }

        $company  = auth()->user()->company;
        $template = $this->service->getOrCreate($company, $type);
        $this->service->reset($template);

        return redirect()
            ->route('email-templates.edit', $type)
            ->with('status', 'Template reset to default.');
    }
}
