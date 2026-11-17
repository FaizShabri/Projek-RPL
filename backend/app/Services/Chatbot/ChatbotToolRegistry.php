<?php

namespace App\Services\Chatbot;

use App\Models\User;
use App\Services\Chatbot\Tools\ChatbotToolInterface;
use App\Services\Chatbot\Tools\Customer\GetCancellationPolicy;
use App\Services\Chatbot\Tools\Customer\GetKurirLocation;
use App\Services\Chatbot\Tools\Customer\GetMyActiveOrders;
use App\Services\Chatbot\Tools\Customer\GetMyNotifications;
use App\Services\Chatbot\Tools\Customer\GetOrderDetail;
use App\Services\Chatbot\Tools\Customer\GetTokoTarif;
use App\Services\Chatbot\Tools\Customer\PrepareDisputeDraft;
use App\Services\Chatbot\Tools\Customer\SearchTokoLaundry;
use App\Services\Chatbot\Tools\OwnerToko\DraftReviewReply;
use App\Services\Chatbot\Tools\OwnerToko\GetTarifComparison;
use App\Services\Chatbot\Tools\OwnerToko\GetTokoDisputes;
use App\Services\Chatbot\Tools\OwnerToko\GetTokoPerformance;
use App\Services\Chatbot\Tools\OwnerToko\GetTokoReviews;
use App\Services\Chatbot\Tools\Admin\DetectAnomalies;
use App\Services\Chatbot\Tools\Admin\GetPlatformAnalytics;
use App\Services\Chatbot\Tools\Admin\GetPlatformDisputes;
use App\Services\Chatbot\Tools\Admin\QueryAuditLogs;
use App\Services\Chatbot\Tools\Admin\SummarizeTokoDocuments;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ChatbotToolRegistry
{
    /**
     * @var array<string, ChatbotToolInterface>
     */
    protected array $tools = [];

    public function __construct()
    {
        $this->registerDefaultTools();
    }

    /**
     * Daftarkan seluruh tool yang ada di sistem.
     */
    protected function registerDefaultTools(): void
    {
        // 1. Customer Tools
        $this->register(new GetMyActiveOrders());
        $this->register(new GetOrderDetail());
        $this->register(new GetKurirLocation());
        $this->register(new SearchTokoLaundry());
        $this->register(new GetTokoTarif());
        $this->register(new GetCancellationPolicy());
        $this->register(new PrepareDisputeDraft());
        $this->register(new GetMyNotifications());

        // 2. Owner Toko Tools
        $this->register(new GetTokoPerformance());
        $this->register(new GetTarifComparison());
        $this->register(new GetTokoReviews());
        $this->register(new DraftReviewReply());
        $this->register(new GetTokoDisputes());

        // 3. Admin Tools (Admin Owner & Platform Owner)
        $this->register(new GetPlatformDisputes());
        $this->register(new GetPlatformAnalytics());
        $this->register(new SummarizeTokoDocuments());
        $this->register(new DetectAnomalies());
        $this->register(new QueryAuditLogs());
    }

    public function register(ChatbotToolInterface $tool): void
    {
        $this->tools[$tool->name()] = $tool;
    }

    /**
     * Mengambil daftar tool yang diizinkan untuk role tertentu.
     * @return ChatbotToolInterface[]
     */
    public function getToolsForRole(string $activeRole): array
    {
        return array_filter($this->tools, function (ChatbotToolInterface $tool) use ($activeRole) {
            return in_array($activeRole, $tool->allowedRoles(), true);
        });
    }

    /**
     * Mengubah tool list ke format definisi function calling / tool use Anthropic.
     */
    public function getAnthropicToolsSchema(string $activeRole): array
    {
        $tools = $this->getToolsForRole($activeRole);
        $schema = [];

        foreach ($tools as $tool) {
            $schema[] = [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'input_schema' => $tool->inputSchema(),
            ];
        }

        return $schema;
    }

    /**
     * Eksekusi tool dengan otorisasi ketat per-function berdasarkan role aktif.
     *
     * @throws AccessDeniedHttpException Jika role tidak berhak menjalankan tool ini.
     * @throws InvalidArgumentException Jika tool tidak terdaftar.
     */
    public function executeTool(string $toolName, array $params, User $user, string $activeRole): array
    {
        if (!isset($this->tools[$toolName])) {
            throw new InvalidArgumentException("Tool '{$toolName}' tidak ditemukan.");
        }

        $tool = $this->tools[$toolName];

        // DEFENSE IN DEPTH: Cek otorisasi role
        if (!in_array($activeRole, $tool->allowedRoles(), true)) {
            throw new AccessDeniedHttpException("Role '{$activeRole}' tidak memiliki izin untuk mengeksekusi tool '{$toolName}'.");
        }

        return $tool->execute($params, $user, $activeRole);
    }
}
