<?php

namespace App\Http\Controllers;

use App\Support\WorkspaceLayoutData;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(private readonly WorkspaceLayoutData $layout) {}

    public function index(Request $request): View
    {
        return view('pages.settings.index', $this->layout->for($request, 'settings.index'));
    }
}
