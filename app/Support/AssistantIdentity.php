<?php

namespace App\Support;

use App\Models\User;

/** Application-owned introductions never need inference or operational queries. */
final class AssistantIdentity
{
    public function answer(User $user, string $kind, string $language = 'en'): string
    {
        $veterinary = $user->isProvincialVeterinaryOffice();
        $filipino = $language === 'fil';
        $limits = $filipino
            ? ($veterinary
                ? 'Animal Health lang ang saklaw ng iyong veterinary account. Maaari akong magpaliwanag ng animal-service workflows at magbasa ng pinapayagang ulat; hindi ako nagbabago o nag-aapruba ng records.'
                : 'Maaari akong magpaliwanag ng AgriLGU at magbasa ng records at ulat na pinapayagan ng iyong role at saklaw. Hindi ako nagbabago ng records, gumagawa ng account o nag-aapruba ng kahilingan.')
            : ($veterinary
                ? 'Your veterinary account is limited to Animal Health. I can explain animal-service workflows and read permitted reports; I cannot change or approve records.'
                : 'I can explain AgriLGU and read records and reports permitted by your role and area. I cannot change records, create accounts or approve requests.');

        $capabilities = $filipino
            ? ($veterinary
                ? 'Matutulungan kitang gamitin ang Animal Health at maintindihan ang pinapayagang ulat. Maaari ko ring ipaliwanag ang access at privacy ng Assistant.'
                : 'Tumutulong ako sa mga feature ng AgriLGU lamang: farmer registry, parcels, crop plotting, assistance, harvests, Animal Health, cooperatives, machinery, dashboard, weather, geofences, accounts, backups at audit tools.')
            : ($veterinary
                ? 'I can help you use Animal Health and understand permitted reports. I can also explain Assistant access and privacy.'
                : 'I help with AgriLGU features only: farmer registry, parcels, crop plotting, assistance, harvests, Animal Health, cooperatives, machinery, dashboard, weather, geofences, accounts, backups and audit tools.');

        return match ($kind) {
            'greeting' => ($filipino ? 'Kumusta! Ako ang AgriLGU Assistant. Ano ang maitutulong ko sa AgriLGU?' : 'Hello! I’m the AgriLGU Assistant. How can I help with AgriLGU?').' '.$limits,
            'thanks' => $filipino ? 'Walang anuman! Sabihin lang kung may iba ka pang tanong tungkol sa AgriLGU.' : 'You’re welcome! Let me know if you have another question about AgriLGU.',
            'capabilities' => $capabilities.' '.$limits.' '.$this->fileLimits($filipino),
            'technology' => $this->technology($filipino).' '.$limits,
            default => ($filipino
                ? 'Ako ang AgriLGU Assistant, isang software assistant na gumagamit ng OpenAI para sa sinuring gabay ng AgriLGU. Ang pagpapakilalang ito ay lokal at walang AI request. Hindi ako opisyal na gumagawa ng pasya o kapalit ng iyong agriculture office.'
                : 'I’m the AgriLGU Assistant, a software assistant using OpenAI for reviewed AgriLGU guidance. This introduction is local and makes no AI request. I am not an official decision-maker or a replacement for your agriculture office.').' '.$limits,
        };
    }

    private function technology(bool $filipino): string
    {
        $model = config('assistant.model');
        // Never echo an arbitrary environment value as a model identifier.
        $model = is_string($model) && preg_match('/^(?:gpt-|o[1-9](?:-|$))[a-z0-9.-]{0,70}$/D', $model) ? $model : null;
        $modelLabel = $model === null ? '' : ' ('.$model.')';

        return $filipino
            ? 'Gumagamit ang gabay sa sistema ng OpenAI'.$modelLabel.' sa pamamagitan ng Laravel AI SDK. Kinukuha ng RAG ang kaugnay na sinuring gabay ng AgriLGU bago sumagot. Aprubadong paksa at sinuring gabay lamang ang ipinapadala sa OpenAI, hindi ang aktuwal na chat o operational record text. Direktang binabasa sa AgriLGU ang pinapayagang office reports. Lokal ang sagot na ito at walang AI request. '.$this->fileLimits(true)
            : 'System guidance uses OpenAI'.$modelLabel.' through the Laravel AI SDK. RAG retrieves relevant reviewed AgriLGU guidance before answering. Only an application-owned topic and reviewed guidance are sent to OpenAI, never raw chat or operational record text. Permitted office reports are read directly inside AgriLGU. This answer is local and makes no AI request. '.$this->fileLimits(false);
    }

    private function fileLimits(bool $filipino): string
    {
        return $filipino
            ? 'Wala akong live browsing, filesystem o execution tools para magbukas ng server files o magpatakbo ng commands sa chat.'
            : 'I have no live browsing, filesystem or execution tools to open server files or run commands through this chat.';
    }
}
