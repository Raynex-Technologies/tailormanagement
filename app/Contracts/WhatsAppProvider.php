<?php

namespace App\Contracts;

use App\Models\WhatsappIntegration;

interface WhatsAppProvider
{
    public function testConnection(WhatsappIntegration $integration): array;

    public function sendText(WhatsappIntegration $integration, string $recipient, string $message): array;

    public function markAsRead(WhatsappIntegration $integration, string $externalMessageId): array;

    public function configureWebhooks(WhatsappIntegration $integration, string $callbackUrl): array;

    public function createTemplate(WhatsappIntegration $integration, \App\Data\WhatsApp\TemplateDefinition $definition): array;

    public function updateTemplate(WhatsappIntegration $integration, string $metaTemplateId, \App\Data\WhatsApp\TemplateDefinition $definition): array;

    public function deleteTemplate(WhatsappIntegration $integration, string $name): array;

    public function listTemplates(WhatsappIntegration $integration, ?string $after = null): array;

    public function uploadTemplateMedia(WhatsappIntegration $integration, string $path, string $mimeType, string $fileName): array;
}
