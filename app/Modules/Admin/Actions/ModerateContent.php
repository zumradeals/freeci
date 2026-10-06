<?php

namespace App\Modules\Admin\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Moderation\ServiceModeration;
use App\Modules\Missions\Actions\MissionModeration;
use Illuminate\Support\Facades\DB;

/**
 * Modération depuis l'interface web : appelle les MÊMES actions métier que les commandes console (verrous, état revérifié, historique,
 * interdiction de modérer son propre contenu) ; ne duplique aucune règle. Chaque appel est journalisé (réussi ou refusé).
 */
final class ModerateContent
{
    private const CHANNEL = 'modération (web)';

    public function __construct(private AdminAudit $audit, private ServiceModeration $services, private MissionModeration $missions) {}

    public function approveService(User $admin, string $versionId): void
    {
        [$id, $label] = $this->serviceOfVersion($versionId);
        $this->audit->run($admin, 'service.approve', 'service', $id, $label, null, fn () => $this->services->via(self::CHANNEL)->approve($admin, $versionId));
    }

    public function refuseService(User $admin, string $versionId, string $reason): void
    {
        [$id, $label] = $this->serviceOfVersion($versionId);
        $this->audit->run($admin, 'service.request_changes', 'service', $id, $label, $reason, fn () => $this->services->via(self::CHANNEL)->requestChanges($admin, $versionId, $reason));
    }

    public function suspendService(User $admin, string $serviceId, string $reason): void
    {
        $this->audit->run($admin, 'service.suspend', 'service', $serviceId, $this->serviceTitle($serviceId), $reason, fn () => $this->services->via(self::CHANNEL)->suspend($admin, $serviceId, $reason));
    }

    public function reinstateService(User $admin, string $serviceId): void
    {
        $this->audit->run($admin, 'service.reinstate', 'service', $serviceId, $this->serviceTitle($serviceId), null, fn () => $this->services->via(self::CHANNEL)->reinstate($admin, $serviceId));
    }

    public function approveMission(User $admin, string $versionId): void
    {
        [$id, $label] = $this->missionOfVersion($versionId);
        $this->audit->run($admin, 'mission.approve', 'mission', $id, $label, null, fn () => $this->missions->via(self::CHANNEL)->approve($admin, $versionId));
    }

    public function refuseMission(User $admin, string $versionId, string $reason): void
    {
        [$id, $label] = $this->missionOfVersion($versionId);
        $this->audit->run($admin, 'mission.request_changes', 'mission', $id, $label, $reason, fn () => $this->missions->via(self::CHANNEL)->requestChanges($admin, $versionId, $reason));
    }

    public function suspendMission(User $admin, string $missionId, string $reason): void
    {
        $this->audit->run($admin, 'mission.suspend', 'mission', $missionId, $this->missionTitle($missionId), $reason, fn () => $this->missions->via(self::CHANNEL)->suspend($admin, $missionId, $reason));
    }

    public function reinstateMission(User $admin, string $missionId): void
    {
        $this->audit->run($admin, 'mission.reinstate', 'mission', $missionId, $this->missionTitle($missionId), null, fn () => $this->missions->via(self::CHANNEL)->reinstate($admin, $missionId));
    }

    /** @return array{0: ?string, 1: ?string} */
    private function serviceOfVersion(string $versionId): array
    {
        $v = DB::table('service_versions')->where('id', $versionId)->first(['service_id', 'title']);

        return [$v?->service_id, $v?->title];
    }

    private function serviceTitle(string $id): ?string
    {
        return DB::table('services')->where('id', $id)->value('title');
    }

    /** @return array{0: ?string, 1: ?string} */
    private function missionOfVersion(string $versionId): array
    {
        $v = DB::table('mission_versions')->where('id', $versionId)->first(['mission_id', 'title']);

        return [$v?->mission_id, $v?->title];
    }

    private function missionTitle(string $id): ?string
    {
        return DB::table('mission_versions')->where('mission_id', $id)->orderByDesc('number')->value('title');
    }
}
