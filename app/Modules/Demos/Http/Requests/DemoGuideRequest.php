<?php

declare(strict_types=1);

namespace App\Modules\Demos\Http\Requests;

use App\Modules\Demos\Contracts\DemoGuide;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

/**
 * The guide's own calls, made by the dock inside the impersonated panel: the signed-in user is the demo's owner or HR, so
 * the gate of the room is asked for the superadmin behind the impersonation (DemoGuide::isPresenter). 404 for anyone else.
 */
final class DemoGuideRequest extends FormRequest
{
    private ?DemoGuide $guide = null;

    public function authorize(DemoGuide $guide): Response
    {
        $this->guide = $guide;

        return $guide->isPresenter() ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }

    /** The guide that authorized the request: who presents is read once per request. */
    public function guide(): DemoGuide
    {
        return $this->guide ?? throw new LogicException('The guide is read once the request is authorized.');
    }
}
