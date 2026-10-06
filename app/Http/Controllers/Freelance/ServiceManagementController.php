<?php

namespace App\Http\Controllers\Freelance;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Actions\ListFreelancerServices;
use App\Modules\Catalog\Actions\ServiceAuthoring;
use App\Modules\Catalog\Actions\ServiceEditorData;
use App\Modules\Catalog\Actions\ServiceImages;
use App\Modules\Catalog\Exceptions\ServiceForbidden;
use App\Modules\Catalog\Exceptions\ServiceStateConflict;
use App\Modules\Files\Exceptions\FileRejected;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** « Mes services » : création, rédaction, aperçu, soumission, retrait. Propriétaire seulement ; toute action revérifie l'état côté serveur. */
class ServiceManagementController extends Controller
{
    private const KINDS = ['soumettre' => 'submit', 'retirer-soumission' => 'unsubmit', 'nouvelle-version' => 'revise', 'retirer-du-catalogue' => 'withdraw', 'remettre-en-ligne' => 'restore'];

    public function __construct(private ServiceAuthoring $authoring, private ServiceEditorData $editor) {}

    private function guard(callable $do, string $success, ?string $to = null, ?string $fallback = null): RedirectResponse
    {
        try {
            $result = $do();
        } catch (ServiceForbidden) {
            abort(404);
        } catch (ServiceStateConflict $e) {
            return redirect($fallback ?? route('freelance.services'))->with('error', $e->getMessage())->withInput();
        } catch (FileRejected $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect($to ?? route('freelance.services'))->with('status', $success);
    }

    public function index(Request $request, ListFreelancerServices $list): View
    {
        return view('freelance.services', ['services' => $list($request->user()), 'space' => 'freelancer', 'profile' => $request->user()->freelanceProfile]);
    }

    public function create(Request $request): View
    {
        return view('freelance.service-new', ['categories' => $this->editor->categoryChoices(), 'space' => 'freelancer', 'limits' => config('freeci.catalog')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['category_id' => ['required', 'string'], 'title' => ['required', 'string', 'max:300']], ['title.required' => 'Donnez un titre à votre service.', 'category_id.required' => 'Choisissez une catégorie.']);
        try {
            $service = $this->authoring->create($request->user(), $data['category_id'], $data['title']);
        } catch (ServiceForbidden) {
            abort(404);
        }

        return redirect()->route('freelance.services.edit', $service->getKey())->with('status', 'Brouillon créé. Complétez le service, puis soumettez-le à modération : il reste invisible du public tant qu’il n’est pas approuvé.');
    }

    public function edit(Request $request, string $service): View|RedirectResponse
    {
        try {
            $d = $this->editor->editor($request->user(), $service);
        } catch (ServiceForbidden) {
            abort(404);
        }
        if ($d['version'] === null) {
            return redirect()->route('freelance.services')->with('error', 'Aucune version en cours de rédaction : choisissez « Modifier » pour en démarrer une.');
        }

        return view('freelance.service-edit', $d + ['space' => 'freelancer']);
    }

    public function update(Request $request, string $service, ServiceImages $images): RedirectResponse
    {
        $request->validate(['revision_no' => ['required', 'integer', 'min:1']]);
        if ($request->file('image') !== null) {
            $request->validate(['image' => ['required', 'file'], 'alt' => ['required', 'string', 'max:160']],
                ['alt.required' => 'Décrivez l’image que vous avez choisie.']);
        }
        $input = $request->all();
        $edit = route('freelance.services.edit', $service);

        $resp = $this->guard(fn () => DB::transaction(function () use ($request, $service, $input, $images) {
            $version = $this->authoring->save($request->user(), $service, $input, (int) $request->input('revision_no'));
            if ($request->hasFile('image')) {
                $images->add($request->user(), $service, $request->file('image'), (string) $request->input('alt'), $version->revision_no);
            }
        }), 'Brouillon enregistré. Il n’est visible que de vous.', $edit, $edit);
        if ($request->input('intent') === 'submit' && ! $resp->getSession()?->has('error')) {
            return redirect()->route('freelance.services.confirm', [$service, 'soumettre']);
        }

        return $resp;
    }

    public function preview(Request $request, string $service): View
    {
        try {
            $detail = $this->editor->preview($request->user(), $service);
        } catch (ServiceForbidden) {
            abort(404);
        }

        return view('catalog.show', ['service' => $detail, 'preview' => route('freelance.services.edit', $service)]);
    }

    /** Pages de confirmation (conséquences exposées avant l'action) : submit | unsubmit | revise | withdraw | restore. */
    public function confirm(Request $request, string $service, string $kind): View|RedirectResponse
    {
        $kind = self::KINDS[$kind];
        try {
            $d = $this->editor->editor($request->user(), $service);
            $problems = $kind === 'submit' ? $this->editor->submissionProblems($request->user(), $service) : [];
        } catch (ServiceForbidden) {
            abort(404);
        }
        $s = $d['service'];
        $ok = match ($kind) {
            'submit' => $d['version']?->isEditable() === true,
            'unsubmit' => $d['version']?->state === 'in_review',
            'revise' => $d['version'] === null && $d['live'] !== null,
            'withdraw' => $s->status->value === 'published',
            'restore' => $s->status->value === 'archived',
        };
        if (! $ok) {
            return redirect()->route('freelance.services')->with('error', 'Cette action n’est plus disponible : le service a changé.');
        }

        return view('freelance.service-step', $d + ['kind' => $kind, 'problems' => $problems, 'space' => 'freelancer']);
    }

    public function act(Request $request, string $service, string $kind): RedirectResponse
    {
        $kind = self::KINDS[$kind];
        $user = $request->user();

        return match ($kind) {
            'submit' => $this->submit($request, $service),
            'unsubmit' => $this->guard(fn () => $this->authoring->withdrawSubmission($user, $service), 'Soumission retirée : le service est de nouveau un brouillon modifiable.', route('freelance.services.edit', $service)),
            'revise' => $this->guard(fn () => $this->authoring->startRevision($user, $service), 'Nouvelle version créée. La version publiée reste en ligne tant que celle-ci n’est pas approuvée.', route('freelance.services.edit', $service)),
            'withdraw' => $this->guard(fn () => $this->authoring->withdrawFromCatalog($user, $service, $request->input('note')), 'Service retiré du catalogue. Les commandes en cours et leurs accords ne sont pas modifiés.'),
            'restore' => $this->guard(fn () => $this->authoring->restoreToCatalog($user, $service), 'Service remis en ligne.'),
        };
    }

    private function submit(Request $request, string $service): RedirectResponse
    {
        $request->validate(['revision_no' => ['required', 'integer', 'min:1']]);

        return $this->guard(fn () => $this->authoring->submit($request->user(), $service, (int) $request->input('revision_no')),
            'Service soumis à modération. Il reste invisible du public jusqu’à son approbation ; vous voyez ici la décision et son motif.');
    }

    public function imageStore(Request $request, string $service, ServiceImages $images): RedirectResponse
    {
        $request->validate(['image' => ['required', 'file'], 'alt' => ['required', 'string', 'max:160'], 'revision_no' => ['required', 'integer']],
            ['image.required' => 'Choisissez une image.', 'alt.required' => 'Décrivez l’image (texte alternatif).']);
        $edit = route('freelance.services.edit', $service);

        return $this->guard(fn () => DB::transaction(function () use ($request, $service, $images) {
            $revision = $this->saveImageForm($request, $service);
            $images->add($request->user(), $service, $request->file('image'), (string) $request->input('alt'), $revision);
        }), 'Image ajoutée. Votre brouillon est enregistré.', $edit.'#images', $edit.'#images');
    }

    public function imageDestroy(Request $request, string $service, string $media, ServiceImages $images): RedirectResponse
    {
        $request->validate(['revision_no' => ['required', 'integer']]);
        $edit = route('freelance.services.edit', $service);

        return $this->guard(fn () => DB::transaction(function () use ($request, $service, $media, $images) {
            $revision = $this->saveImageForm($request, $service);
            $images->remove($request->user(), $service, $media, $revision);
        }), 'Image retirée. Votre brouillon est enregistré.', $edit.'#images', $edit.'#images');
    }

    private function saveImageForm(Request $request, string $service): int
    {
        $revision = (int) $request->input('revision_no');
        // Les anciens clients qui n’envoient que l’image restent compatibles.
        if ($request->has('editor_form')) {
            return $this->authoring->save($request->user(), $service, $request->all(), $revision)->revision_no;
        }

        return $revision;
    }
}
