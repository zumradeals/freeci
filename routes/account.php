<?php

use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\Account\DashboardController;
use App\Http\Controllers\Account\ProfilePhotoController;
use App\Http\Controllers\Admin\ActivationController;
use App\Http\Controllers\Admin\AdminHomeController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\CategoriesController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\LegalPagesController;
use App\Http\Controllers\Admin\MfaController;
use App\Http\Controllers\Admin\ModerationController;
use App\Http\Controllers\Admin\NavigationController;
use App\Http\Controllers\Admin\OperationsController;
use App\Http\Controllers\Admin\ReauthController;
use App\Http\Controllers\Admin\ReconciliationController;
use App\Http\Controllers\Admin\ReviewModerationController;
use App\Http\Controllers\Admin\SecurityController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SupportCaseController;
use App\Http\Controllers\Admin\SupportTeamController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ClientMissionController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\FinanceSpaceController;
use App\Http\Controllers\Freelance\FreelanceController;
use App\Http\Controllers\Freelance\PortfolioController;
use App\Http\Controllers\Freelance\ServiceManagementController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderFileController;
use App\Http\Controllers\OrderRequestController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProposalController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\Support\AssistanceController;
use App\Http\Controllers\Support\DisputeController;
use Illuminate\Support\Facades\Route;

// Espace privé : toute route ici exige une session authentifiée ; réponses jamais mises en cache partagé.
Route::middleware(['auth', 'no-store'])->group(function () {
    Route::get('/espace', DashboardController::class)->name('account.dashboard');
    // Compte : informations, mot de passe, adresse (vérifiée avant remplacement), sessions, export privé, fermeture.
    Route::get('/espace/compte', [AccountController::class, 'show'])->name('account.settings');
    Route::post('/espace/compte/nom', [AccountController::class, 'name'])->middleware('throttle:20,1')->name('account.name');
    Route::post('/espace/compte/mot-de-passe', [AccountController::class, 'password'])->middleware('throttle:6,1')->name('account.password');
    Route::post('/espace/compte/adresse', [AccountController::class, 'requestEmail'])->middleware('throttle:5,10')->name('account.email.request');
    Route::get('/espace/compte/adresse/{id}/{token}', [AccountController::class, 'confirmEmail'])->middleware(['signed', 'throttle:10,1'])->name('account.email.confirm');
    Route::post('/espace/compte/sessions/autres/fermer', [AccountController::class, 'revokeOthers'])->middleware('throttle:10,1')->name('account.sessions.revoke-others');
    Route::post('/espace/compte/sessions/{id}/fermer', [AccountController::class, 'revokeSession'])->middleware('throttle:20,1')->name('account.sessions.revoke');
    Route::post('/espace/compte/photo', [ProfilePhotoController::class, 'store'])->middleware('throttle:10,10')->name('account.photo.store');
    Route::post('/espace/compte/photo/supprimer', [ProfilePhotoController::class, 'destroy'])->middleware('throttle:20,10')->name('account.photo.destroy');
    Route::post('/espace/compte/export', [AccountController::class, 'export'])->middleware('throttle:6,10')->name('account.export');
    Route::post('/espace/compte/fermeture', [AccountController::class, 'requestClosure'])->middleware('throttle:5,10')->name('account.closure.request');
    Route::post('/espace/compte/fermeture/annuler', [AccountController::class, 'cancelClosure'])->middleware('throttle:10,1')->name('account.closure.cancel');
    Route::get('/espace/commandes', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/espace/finances', [FinanceSpaceController::class, 'client'])->name('client.finances');
    // Favoris privés (service ou freelance), sans doublon ; jamais visibles des autres.
    Route::get('/espace/favoris', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('/favoris/{kind}/{slug}', [FavoriteController::class, 'toggle'])->whereIn('kind', ['service', 'freelance'])->middleware('throttle:60,1')->name('favorites.toggle');
    Route::post('/espace/favoris/{id}/retirer', [FavoriteController::class, 'remove'])->whereNumber('id')->middleware('throttle:60,1')->name('favorites.remove');

    // Demande de prestation (client) — la reprise après connexion revient ici (URL « intended » interne).
    Route::get('/services/{slug}/demande', [OrderRequestController::class, 'create'])->name('services.request');
    Route::post('/services/{slug}/demande', [OrderRequestController::class, 'store'])->middleware('throttle:12,1')->name('services.request.store');

    // Dossier commun : lecture bornée aux deux parties ; chaque transition est une action serveur confirmée.
    Route::get('/commandes/{reference}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/commandes/{reference}/{action}', [OrderController::class, 'confirm'])->whereIn('action', ['accept', 'decline', 'withdraw', 'cancel'])->name('orders.confirm');
    Route::post('/commandes/{reference}/{action}', [OrderController::class, 'act'])->whereIn('action', ['accept', 'decline', 'withdraw', 'cancel'])->middleware('throttle:30,1')->name('orders.act');

    // Paiement SIMULÉ (réservé aux commandes et comptes de démonstration autorisés) : état lu en base, jamais déduit de l'URL.
    Route::get('/commandes/{reference}/paiement', [PaymentController::class, 'show'])->name('orders.payment');
    Route::post('/commandes/{reference}/paiement', [PaymentController::class, 'pay'])->middleware('throttle:10,1')->name('orders.payment.start');
    Route::get('/commandes/{reference}/paiement/retour', [PaymentController::class, 'back'])->middleware('throttle:30,1')->name('orders.payment.return');
    Route::post('/commandes/{reference}/paiement/actualiser', [PaymentController::class, 'refresh'])->middleware('throttle:20,1')->name('orders.payment.refresh');

    // Pièces jointes privées du brief.
    Route::post('/commandes/{reference}/brief/fichiers', [OrderFileController::class, 'store'])->middleware('throttle:20,1')->name('orders.files.store');
    Route::post('/commandes/{reference}/brief/fichiers/{file}/retirer', [OrderFileController::class, 'destroy'])->name('orders.files.destroy');
    Route::get('/commandes/{reference}/fichiers/{file}', [OrderFileController::class, 'download'])->middleware('signed')->name('orders.files.download');

    // Messagerie privée : participants seulement. Les routes fixes passent avant {conversation}.
    Route::get('/espace/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('/espace/messages/fichiers/{file}', [MessageController::class, 'download'])->middleware('signed')->name('messages.files.download');
    Route::get('/espace/messages/{conversation}', [MessageController::class, 'show'])->name('messages.show');
    Route::post('/espace/messages/{conversation}', [MessageController::class, 'store'])->middleware('throttle:30,1')->name('messages.send');
    Route::post('/espace/messages/{conversation}/bloquer', [MessageController::class, 'block'])->middleware('throttle:20,1')->name('messages.block');
    Route::post('/espace/messages/{conversation}/debloquer', [MessageController::class, 'unblock'])->middleware('throttle:20,1')->name('messages.unblock');
    Route::get('/services/{slug}/contacter', [MessageController::class, 'startService'])->name('messages.start.service');
    Route::post('/services/{slug}/contacter', [MessageController::class, 'storeService'])->middleware('throttle:10,1')->name('messages.start.service.store');
    Route::get('/espace/propositions/{proposal}/message', [MessageController::class, 'startProposal'])->name('messages.start.proposal');
    Route::post('/espace/propositions/{proposal}/message', [MessageController::class, 'storeProposal'])->middleware('throttle:10,1')->name('messages.start.proposal.store');
    Route::get('/commandes/{reference}/messages', [MessageController::class, 'order'])->name('messages.order');

    // Notifications : centre, lecture, préférences. Bornées à l'utilisateur connecté.
    Route::get('/espace/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/espace/notifications/preferences', [NotificationController::class, 'preferences'])->name('notifications.preferences');
    Route::post('/espace/notifications/preferences', [NotificationController::class, 'savePreferences'])->middleware('throttle:20,1')->name('notifications.preferences.save');
    Route::post('/espace/notifications/tout-lire', [NotificationController::class, 'readAll'])->middleware('throttle:20,1')->name('notifications.read-all');
    Route::get('/notifications/{id}/ouvrir', [NotificationController::class, 'open'])->whereNumber('id')->name('notifications.open');
    Route::post('/notifications/{id}/lire', [NotificationController::class, 'read'])->whereNumber('id')->middleware('throttle:60,1')->name('notifications.read');

    // Missions (client) : propriétaire seulement. Les routes fixes passent avant les routes à paramètre.
    Route::get('/espace/missions', [ClientMissionController::class, 'index'])->name('client.missions');
    Route::get('/espace/missions/nouvelle', [ClientMissionController::class, 'create'])->name('client.missions.new');
    Route::post('/espace/missions', [ClientMissionController::class, 'store'])->middleware('throttle:20,1')->name('client.missions.store');
    Route::get('/espace/missions/{mission}', [ClientMissionController::class, 'show'])->name('client.missions.show');
    Route::get('/espace/missions/{mission}/modifier', [ClientMissionController::class, 'edit'])->name('client.missions.edit');
    Route::post('/espace/missions/{mission}/modifier', [ClientMissionController::class, 'update'])->middleware('throttle:60,1')->name('client.missions.update');
    Route::get('/espace/missions/{mission}/apercu', [ClientMissionController::class, 'preview'])->name('client.missions.preview');
    Route::get('/espace/missions/{mission}/propositions', [ClientMissionController::class, 'proposals'])->name('client.missions.proposals');
    Route::get('/espace/missions/{mission}/propositions/{version}/choisir', [ClientMissionController::class, 'selectForm'])->name('client.missions.select');
    Route::post('/espace/missions/{mission}/propositions/{version}/choisir', [ClientMissionController::class, 'select'])->middleware('throttle:10,1')->name('client.missions.select.store');
    Route::get('/espace/missions/{mission}/{kind}', [ClientMissionController::class, 'confirm'])->whereIn('kind', ['soumettre', 'retirer-soumission', 'nouvelle-version', 'fermer', 'annuler', 'rouvrir'])->name('client.missions.confirm');
    Route::post('/espace/missions/{mission}/{kind}', [ClientMissionController::class, 'act'])->whereIn('kind', ['soumettre', 'retirer-soumission', 'nouvelle-version', 'fermer', 'annuler', 'rouvrir'])->middleware('throttle:20,1')->name('client.missions.act');

    // Propositions (freelance) : auteur seulement.
    Route::get('/missions/{slug}/proposition', [ProposalController::class, 'form'])->name('missions.proposal');
    Route::post('/missions/{slug}/proposition', [ProposalController::class, 'store'])->middleware('throttle:20,1')->name('missions.proposal.store');

    // Livraison, corrections, report d'échéance, validation. Les routes fixes passent AVANT {action} (contraint) : aucun conflit.
    Route::get('/commandes/{reference}/livraison', [DeliveryController::class, 'edit'])->name('orders.delivery');
    Route::post('/commandes/{reference}/livraison/message', [DeliveryController::class, 'saveMessage'])->middleware('throttle:30,1')->name('orders.delivery.message');
    Route::post('/commandes/{reference}/livraison/fichiers', [DeliveryController::class, 'upload'])->middleware('throttle:20,1')->name('orders.delivery.upload');
    Route::post('/commandes/{reference}/livraison/fichiers/{file}/retirer', [DeliveryController::class, 'removeFile'])->name('orders.delivery.remove');
    Route::get('/commandes/{reference}/livraison/soumettre', [DeliveryController::class, 'submitConfirm'])->name('orders.delivery.confirm');
    Route::post('/commandes/{reference}/livraison/soumettre', [DeliveryController::class, 'submit'])->middleware('throttle:10,1')->name('orders.delivery.submit');
    Route::get('/commandes/{reference}/correction', [DeliveryController::class, 'correctionForm'])->name('orders.correction');
    Route::post('/commandes/{reference}/correction', [DeliveryController::class, 'correction'])->middleware('throttle:10,1')->name('orders.correction.store');
    Route::get('/commandes/{reference}/desaccord', [DeliveryController::class, 'disagreementForm'])->name('orders.disagreement');
    Route::post('/commandes/{reference}/desaccord', [DeliveryController::class, 'disagreement'])->middleware('throttle:10,1')->name('orders.disagreement.store');
    Route::get('/commandes/{reference}/validation', [DeliveryController::class, 'validateForm'])->name('orders.validation');
    Route::post('/commandes/{reference}/validation', [DeliveryController::class, 'validateDelivery'])->middleware('throttle:10,1')->name('orders.validation.store');
    Route::get('/commandes/{reference}/report', [DeliveryController::class, 'extensionForm'])->name('orders.extension');
    Route::post('/commandes/{reference}/report', [DeliveryController::class, 'extension'])->middleware('throttle:10,1')->name('orders.extension.store');
    Route::post('/commandes/{reference}/report/retirer', [DeliveryController::class, 'extensionWithdraw'])->middleware('throttle:10,1')->name('orders.extension.withdraw');
    Route::get('/commandes/{reference}/report/{decision}', [DeliveryController::class, 'extensionAnswerForm'])->whereIn('decision', ['accepter', 'refuser'])->name('orders.extension.answer');
    Route::post('/commandes/{reference}/report/{decision}', [DeliveryController::class, 'extensionAnswer'])->whereIn('decision', ['accepter', 'refuser'])->middleware('throttle:10,1')->name('orders.extension.answer.store');

    // Avis : le client de la commande dépose (une fois) ; le freelance évalué répond (une fois).
    Route::get('/commandes/{reference}/avis', [ReviewController::class, 'show'])->name('orders.review');
    Route::post('/commandes/{reference}/avis', [ReviewController::class, 'store'])->middleware('throttle:10,1')->name('orders.review.store');
    Route::post('/avis/{review}/reponse', [ReviewController::class, 'reply'])->whereUuid('review')->middleware('throttle:10,1')->name('reviews.reply');

    // Espace freelance : activation puis pages réservées au rôle freelance.
    Route::get('/freelance/activer', [FreelanceController::class, 'activate'])->name('freelance.activate');
    Route::post('/freelance/profil', [FreelanceController::class, 'save'])->middleware('throttle:20,1')->name('freelance.profile.save');
    Route::middleware('freelance')->prefix('freelance')->group(function () {
        Route::get('/', [FreelanceController::class, 'dashboard'])->name('freelance.dashboard');
        Route::get('/commandes', [FreelanceController::class, 'orders'])->name('freelance.orders');
        Route::get('/revenus', [FinanceSpaceController::class, 'freelancer'])->name('freelance.earnings');
        Route::post('/revenus/destination', [FinanceSpaceController::class, 'declareBeneficiary'])->middleware('throttle:10,10')->name('freelance.earnings.beneficiary');
        Route::get('/services', [ServiceManagementController::class, 'index'])->name('freelance.services');
        Route::get('/services/nouveau', [ServiceManagementController::class, 'create'])->name('freelance.services.new');
        Route::post('/services', [ServiceManagementController::class, 'store'])->middleware('throttle:20,1')->name('freelance.services.store');
        Route::get('/services/{service}/modifier', [ServiceManagementController::class, 'edit'])->name('freelance.services.edit');
        Route::post('/services/{service}/modifier', [ServiceManagementController::class, 'update'])->middleware('throttle:60,1')->name('freelance.services.update');
        Route::get('/services/{service}/apercu', [ServiceManagementController::class, 'preview'])->name('freelance.services.preview');
        Route::post('/services/{service}/images', [ServiceManagementController::class, 'imageStore'])->middleware('throttle:30,1')->name('freelance.services.images.store');
        Route::post('/services/{service}/images/{media}/retirer', [ServiceManagementController::class, 'imageDestroy'])->name('freelance.services.images.destroy');
        Route::get('/services/{service}/{kind}', [ServiceManagementController::class, 'confirm'])->whereIn('kind', ['soumettre', 'retirer-soumission', 'nouvelle-version', 'retirer-du-catalogue', 'remettre-en-ligne'])->name('freelance.services.confirm');
        Route::post('/services/{service}/{kind}', [ServiceManagementController::class, 'act'])->whereIn('kind', ['soumettre', 'retirer-soumission', 'nouvelle-version', 'retirer-du-catalogue', 'remettre-en-ligne'])->middleware('throttle:20,1')->name('freelance.services.act');
        Route::post('/realisations', [PortfolioController::class, 'store'])->middleware('throttle:20,10')->name('freelance.portfolio.store');
        Route::post('/realisations/{id}', [PortfolioController::class, 'update'])->middleware('throttle:30,10')->name('freelance.portfolio.update');
        Route::post('/realisations/{id}/supprimer', [PortfolioController::class, 'destroy'])->middleware('throttle:30,10')->name('freelance.portfolio.destroy');
        Route::post('/profil/publier', [FreelanceController::class, 'publish'])->middleware('throttle:10,1')->name('freelance.profile.publish');
        Route::get('/profil', [FreelanceController::class, 'profile'])->name('freelance.profile');
        Route::get('/propositions', [ProposalController::class, 'index'])->name('freelance.proposals');
        Route::get('/propositions/{proposal}/retirer', [ProposalController::class, 'withdrawForm'])->name('freelance.proposals.withdraw');
        Route::post('/propositions/{proposal}/retirer', [ProposalController::class, 'withdraw'])->middleware('throttle:10,1')->name('freelance.proposals.withdraw.store');
    });
});

// Assistance, signalements et litiges (espace connecté). Un compte suspendu garde l'accès à ses dossiers et peut contacter le support.
Route::middleware(['auth', 'no-store'])->group(function () {
    Route::get('/espace/assistance', [AssistanceController::class, 'index'])->name('support.index');
    Route::get('/espace/assistance/nouvelle', [AssistanceController::class, 'create'])->name('support.new');
    Route::post('/espace/assistance', [AssistanceController::class, 'store'])->middleware('throttle:10,10')->name('support.store');
    Route::get('/espace/assistance/signaler/{type}/{id}', [AssistanceController::class, 'reportForm'])->name('support.report.form');
    Route::post('/espace/assistance/signaler/{type}/{id}', [AssistanceController::class, 'report'])->middleware('throttle:10,10')->name('support.report');
    Route::get('/espace/assistance/fichiers/{file}', [AssistanceController::class, 'download'])->middleware('signed')->name('support.files.download');
    Route::get('/espace/assistance/{reference}', [AssistanceController::class, 'show'])->name('support.show');
    Route::post('/espace/assistance/{reference}/reponse', [AssistanceController::class, 'reply'])->middleware('throttle:20,1')->name('support.reply');
    Route::get('/commandes/{reference}/litige', [DisputeController::class, 'create'])->name('orders.dispute');
    Route::post('/commandes/{reference}/litige', [DisputeController::class, 'store'])->middleware('throttle:6,10')->name('orders.dispute.store');
});

// Vérification de l'adresse par courriel (lien signé, valable pour l'utilisateur connecté qu'il désigne).
Route::middleware(['auth', 'no-store'])->group(function () {
    Route::get('/espace/verification-adresse/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware(['signed', 'throttle:10,1'])->name('verification.verify');
});

// Administration, séparée des espaces client et freelance. « staff » : habilitation (administrateur ou support) en vigueur revérifiée à chaque requête (404 sinon).
// Activation (adresse vérifiée + double authentification) et défi de session accessibles avant « admin-ready » ; tout le reste l'exige.
Route::middleware(['auth', 'no-store', 'staff'])->prefix('admin')->group(function () {
    Route::get('/activation', [ActivationController::class, 'show'])->name('admin.activation');
    Route::post('/activation/courriel', [ActivationController::class, 'sendEmail'])->middleware('throttle:3,10')->name('admin.activation.email');
    Route::post('/activation/mfa', [ActivationController::class, 'begin'])->middleware('throttle:10,10')->name('admin.activation.begin');
    Route::post('/activation/mfa/valider', [ActivationController::class, 'confirm'])->middleware('throttle:10,10')->name('admin.activation.enable');
    Route::get('/verification', [MfaController::class, 'show'])->name('admin.mfa');
    Route::post('/verification', [MfaController::class, 'verify'])->middleware('throttle:15,5')->name('admin.mfa.verify');

    Route::middleware('admin-ready')->group(function () {
        Route::get('/', AdminHomeController::class)->name('admin.home');
        Route::get('/confirmation', [ReauthController::class, 'show'])->name('admin.reauth');
        Route::post('/confirmation', [ReauthController::class, 'confirm'])->middleware('throttle:15,5')->name('admin.reauth.confirm');
        Route::get('/securite', [SecurityController::class, 'show'])->name('admin.security');
        Route::post('/securite/codes', [SecurityController::class, 'regenerate'])->middleware(['recent-auth', 'throttle:5,10'])->name('admin.security.codes');

        // Assistance : administrateurs ET personnel « support ».
        Route::get('/assistance', [SupportCaseController::class, 'index'])->name('admin.support');
        Route::post('/assistance/suivis/{id}/dossier', [SupportCaseController::class, 'fromFollowUp'])->whereNumber('id')->middleware('throttle:30,1')->name('admin.support.followup');
        Route::get('/assistance/{reference}/fichiers/{file}', [SupportCaseController::class, 'download'])->middleware('signed')->name('admin.support.files.download');
        Route::get('/assistance/{reference}', [SupportCaseController::class, 'show'])->name('admin.support.show');
        Route::middleware('throttle:60,1')->group(function () {
            Route::post('/assistance/{reference}/prendre', [SupportCaseController::class, 'claim'])->name('admin.support.claim');
            Route::post('/assistance/{reference}/affecter', [SupportCaseController::class, 'assign'])->name('admin.support.assign');
            Route::post('/assistance/{reference}/liberer', [SupportCaseController::class, 'release'])->name('admin.support.release');
            Route::post('/assistance/{reference}/etat', [SupportCaseController::class, 'status'])->name('admin.support.status');
            Route::post('/assistance/{reference}/reponse', [SupportCaseController::class, 'reply'])->name('admin.support.reply');
            Route::post('/assistance/{reference}/cloture', [SupportCaseController::class, 'close'])->name('admin.support.close');
        });
        // Ouvrir le dossier (motif) et décider : confirmation récente d'identité exigée.
        Route::post('/assistance/{reference}/ouvrir', [SupportCaseController::class, 'open'])->middleware(['recent-auth', 'throttle:30,1'])->name('admin.support.open');
        Route::post('/assistance/{reference}/decision', [SupportCaseController::class, 'decide'])->middleware(['recent-auth', 'throttle:20,1'])->name('admin.support.decide');

        // Réservé aux administrateurs : modération, utilisateurs, journal d'audit.
        Route::middleware('administrator')->group(function () {
            Route::get('/moderation', [ModerationController::class, 'index'])->name('admin.moderation');
            Route::get('/moderation/services/{version}', [ModerationController::class, 'service'])->name('admin.moderation.service');
            Route::get('/moderation/missions/{version}', [ModerationController::class, 'mission'])->name('admin.moderation.mission');
            Route::get('/moderation/en-ligne/service/{id}', [ModerationController::class, 'liveService'])->name('admin.moderation.live.service');
            Route::get('/moderation/en-ligne/mission/{id}', [ModerationController::class, 'liveMission'])->name('admin.moderation.live.mission');
            Route::post('/moderation/{kind}/{version}/{decision}', [ModerationController::class, 'decide'])->whereIn('decision', ['approuver', 'refuser'])->middleware('throttle:30,1')->name('admin.moderation.decide');
            Route::post('/moderation/en-ligne/{kind}/{id}/{action}', [ModerationController::class, 'toggle'])->whereIn('action', ['suspendre', 'remettre'])->middleware(['recent-auth', 'throttle:30,1'])->name('admin.moderation.toggle');

            Route::get('/utilisateurs', [UserController::class, 'index'])->name('admin.users');
            Route::get('/utilisateurs/{id}', [UserController::class, 'show'])->name('admin.users.show');
            Route::post('/utilisateurs/{id}/realisations/{item}/retirer', [UserController::class, 'removePortfolio'])->whereUuid('item')->middleware(['recent-auth', 'throttle:20,1'])->name('admin.users.portfolio.remove');
            Route::post('/utilisateurs/{id}/photo/retirer', [UserController::class, 'removePhoto'])->middleware(['recent-auth', 'throttle:20,1'])->name('admin.users.photo.remove');
            Route::post('/utilisateurs/{id}/{action}', [UserController::class, 'change'])->whereIn('action', ['suspendre', 'reactiver'])->middleware(['recent-auth', 'throttle:20,1'])->name('admin.users.change');

            Route::get('/paiements', [ReconciliationController::class, 'index'])->name('admin.payments');
            Route::post('/paiements/{id}/examiner', [ReconciliationController::class, 'review'])->middleware('throttle:30,1')->name('admin.payments.review');

            Route::get('/exploitation', OperationsController::class)->name('admin.operations');
            Route::post('/exploitation/demo/installer', [OperationsController::class, 'installDemo'])->middleware(['recent-auth', 'throttle:3,10'])->name('admin.operations.install-demo');
            Route::post('/exploitation/demo/retirer', [OperationsController::class, 'purgeDemo'])->middleware(['recent-auth', 'throttle:3,10'])->name('admin.operations.purge-demo');

            // Paramètres de la plateforme et textes légaux : modifiables par l'administrateur (confirmation récente d'identité à chaque écriture, historique, audit).
            Route::get('/parametres', [SettingsController::class, 'index'])->name('admin.settings');
            Route::post('/parametres/{group}', [SettingsController::class, 'save'])->middleware(['recent-auth', 'throttle:20,1'])->name('admin.settings.save');
            Route::post('/parametres-test/courrier', [SettingsController::class, 'testMail'])->middleware(['recent-auth', 'throttle:5,1'])->name('admin.settings.test-mail');
            Route::post('/parametres-test/genius/{env}', [SettingsController::class, 'testGenius'])->middleware(['recent-auth', 'throttle:5,1'])->name('admin.settings.test-genius');
            Route::get('/equipe', [SupportTeamController::class, 'index'])->name('admin.team');
            Route::post('/equipe', [SupportTeamController::class, 'grant'])->middleware(['recent-auth', 'throttle:10,1'])->name('admin.team.grant');
            Route::post('/equipe/{id}/retirer', [SupportTeamController::class, 'revoke'])->whereUuid('id')->middleware(['recent-auth', 'throttle:10,1'])->name('admin.team.revoke');
            Route::post('/equipe/administrateurs', [SupportTeamController::class, 'grantAdmin'])->middleware(['recent-auth', 'throttle:5,1'])->name('admin.team.admin.grant');
            Route::post('/equipe/administrateurs/{id}/retirer', [SupportTeamController::class, 'revokeAdmin'])->whereUuid('id')->middleware(['recent-auth', 'throttle:5,1'])->name('admin.team.admin.revoke');
            Route::get('/navigation', [NavigationController::class, 'index'])->name('admin.navigation');
            Route::middleware(['recent-auth', 'throttle:30,1'])->group(function () {
                Route::post('/navigation/{area}/personnaliser', [NavigationController::class, 'customize'])->name('admin.navigation.customize');
                Route::post('/navigation/{area}/ajouter', [NavigationController::class, 'add'])->name('admin.navigation.add');
                Route::post('/navigation/{area}/retablir', [NavigationController::class, 'reset'])->name('admin.navigation.reset');
                Route::post('/navigation/liens/{id}', [NavigationController::class, 'update'])->whereNumber('id')->name('admin.navigation.update');
                Route::post('/navigation/liens/{id}/deplacer/{direction}', [NavigationController::class, 'move'])->whereNumber('id')->whereIn('direction', ['up', 'down'])->name('admin.navigation.move');
                Route::post('/navigation/liens/{id}/supprimer', [NavigationController::class, 'delete'])->whereNumber('id')->name('admin.navigation.delete');
            });
            Route::get('/categories', [CategoriesController::class, 'index'])->name('admin.categories');
            Route::middleware(['recent-auth', 'throttle:30,1'])->group(function () {
                Route::post('/categories', [CategoriesController::class, 'store'])->name('admin.categories.store');
                Route::post('/categories/{id}', [CategoriesController::class, 'update'])->whereUuid('id')->name('admin.categories.update');
                Route::post('/categories/{id}/deplacer/{direction}', [CategoriesController::class, 'move'])->whereUuid('id')->whereIn('direction', ['up', 'down'])->name('admin.categories.move');
                Route::post('/categories/{id}/mettre-en-avant', [CategoriesController::class, 'feature'])->whereUuid('id')->name('admin.categories.feature');
                Route::post('/categories/{id}/retirer-mise-en-avant', [CategoriesController::class, 'unfeature'])->whereUuid('id')->name('admin.categories.unfeature');
                Route::post('/categories/{id}/archiver', [CategoriesController::class, 'archive'])->whereUuid('id')->name('admin.categories.archive');
                Route::post('/categories/{id}/retablir', [CategoriesController::class, 'restore'])->whereUuid('id')->name('admin.categories.restore');
                Route::post('/categories/{id}/supprimer', [CategoriesController::class, 'destroy'])->whereUuid('id')->name('admin.categories.delete');
            });
            Route::get('/pages', [LegalPagesController::class, 'index'])->name('admin.legal');
            Route::get('/pages/{slug}', [LegalPagesController::class, 'edit'])->name('admin.legal.edit');
            Route::middleware(['recent-auth', 'throttle:20,1'])->group(function () {
                Route::post('/pages/{slug}/brouillon', [LegalPagesController::class, 'draft'])->name('admin.legal.draft');
                Route::post('/pages/{slug}/publier', [LegalPagesController::class, 'publish'])->name('admin.legal.publish');
                Route::post('/pages/{slug}/retirer', [LegalPagesController::class, 'withdraw'])->name('admin.legal.withdraw');
            });

            // Modération des avis : masquer / rétablir avec catégorie, motif et historique (confirmation récente d'identité).
            Route::get('/avis', [ReviewModerationController::class, 'index'])->name('admin.reviews');
            Route::post('/avis/{review}/{action}', [ReviewModerationController::class, 'act'])->whereUuid('review')->whereIn('action', ['masquer', 'retablir'])->middleware(['recent-auth', 'throttle:30,1'])->name('admin.reviews.act');

            // Finances : remboursements, commissions, reversements. Lecture : administrateurs ; écriture : confirmation récente d'identité à chaque action.
            Route::get('/finances', [FinanceController::class, 'index'])->name('admin.finance');
            Route::get('/finances/operations/{reference}', [FinanceController::class, 'show'])->name('admin.finance.show');
            Route::middleware(['recent-auth', 'throttle:20,1'])->group(function () {
                Route::post('/finances/decisions/{decision}/rembourser', [FinanceController::class, 'requestRefund'])->whereNumber('decision')->name('admin.finance.refund');
                Route::post('/finances/commandes/{reference}/reverser', [FinanceController::class, 'requestPayout'])->name('admin.finance.payout');
                Route::post('/finances/operations/{reference}/{action}', [FinanceController::class, 'act'])->whereIn('action', ['confirmer', 'refuser', 'annuler', 'executer-api', 'enregistrer', 'rapprocher'])->name('admin.finance.act');
                Route::post('/finances/destinations/{id}/{action}', [FinanceController::class, 'beneficiary'])->whereIn('action', ['verifier', 'desactiver'])->name('admin.finance.beneficiary');
            });

            Route::get('/journal', [AuditController::class, 'actions'])->name('admin.audit');
            Route::get('/journal/securite', [AuditController::class, 'security'])->name('admin.audit.security');
        });
    });
});
