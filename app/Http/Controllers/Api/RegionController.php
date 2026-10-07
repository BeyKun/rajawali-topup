<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Admin\RegionController as BaseRegionController;

/**
 * Public region dropdown endpoints for the mobile onboarding form.
 *
 * Reuses the dashboard implementation verbatim (same search + pagination
 * contract) but is exposed without authentication so the profile completion
 * screen can populate its dependent selects immediately after Google sign-in.
 */
class RegionController extends BaseRegionController {}
