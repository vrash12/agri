<?php

namespace App\Support;

use App\Models\User;

/** Application-owned introductions never need inference or operational queries. */
final class AssistantIdentity
{
    public function answer(User $user, string $kind, string $language = 'en', string $module = 'other'): string
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
        $pageContext = $this->pageContext($module, $filipino);

        return match ($kind) {
            'greeting' => ($filipino ? 'Kumusta! Ako ang AgriLGU Assistant. Ano ang maitutulong ko sa AgriLGU?' : 'Hello! I’m the AgriLGU Assistant. How can I help with AgriLGU?').' '.$limits,
            'thanks' => $filipino ? 'Walang anuman! Sabihin lang kung may iba ka pang tanong tungkol sa AgriLGU.' : 'You’re welcome! Let me know if you have another question about AgriLGU.',
            'capabilities' => $capabilities.' '.$pageContext.' '.$limits.' '.$this->fileLimits($filipino),
            'context' => $pageContext.' '.$limits,
            'account' => $this->accountContext($user, $filipino).' '.$limits,
            'awareness' => $this->awareness($filipino).' '.$pageContext.' '.$limits,
            'technology' => $this->technology($filipino).' '.$limits,
            default => ($filipino
                ? 'Ako ang AgriLGU Assistant, isang software assistant na gumagamit ng OpenAI para sa sinuring gabay ng AgriLGU. Ang pagpapakilalang ito ay lokal at walang AI request. Hindi ako opisyal na gumagawa ng pasya o kapalit ng iyong agriculture office.'
                : 'I’m the AgriLGU Assistant, a software assistant using OpenAI for reviewed AgriLGU guidance. This introduction is local and makes no AI request. I am not an official decision-maker or a replacement for your agriculture office.').' '.$pageContext.' '.$limits,
        };
    }

    private function pageContext(string $module, bool $filipino): string
    {
        $label = match ($module) {
            'dashboard' => 'Dashboard',
            'farmers' => $filipino ? 'farmer registry at farmer records' : 'Farmers and farmer registry',
            'crops' => $filipino ? 'crop at parcel mapping' : 'Crop and parcel mapping',
            'satellite' => 'Satellite / NDVI',
            'assistance' => $filipino ? 'agriculture assistance at seed releases' : 'Assistance and seed releases',
            'coverage' => $filipino ? 'assistance coverage' : 'Assistance coverage',
            'harvests' => $filipino ? 'harvest records' : 'Harvest records',
            'animal-health' => 'Animal Health',
            'cooperatives' => $filipino ? 'farmer cooperatives' : 'Farmer cooperatives',
            'machinery' => $filipino ? 'machinery inventory' : 'Machinery inventory',
            'accounts' => $filipino ? 'office accounts' : 'Office accounts',
            'geofences' => $filipino ? 'municipality geofences' : 'Municipality geofences',
            'backups' => 'Backup Folder',
            'audit' => $filipino ? 'audit trail' : 'Audit trail',
            default => null,
        };

        if ($label === null) {
            return $filipino
                ? 'Walang tiyak na pahinang ibinahagi sa chat na ito. Maaari pa rin akong tumulong sa sinuring gabay ng AgriLGU.'
                : 'No specific page has been shared with this chat. I can still help with reviewed AgriLGU guidance.';
        }

        // A browser-supplied module is only a relevance hint, never evidence of
        // the active filters, unsaved fields or permission to access that page.
        return $filipino
            ? 'Ang pahinang ibinahagi sa chat na ito ay '.$label.'. Gabay lang ito; hindi ko nakikita ang filters o hindi pa na-save na fields. Role at saklaw ng opisina mo pa rin ang kumokontrol sa access.'
            : 'The page shared with this chat is '.$label.'. It guides my help; I cannot see active filters or unsaved fields. Your role and office scope still control access.';
    }

    private function accountContext(User $user, bool $filipino): string
    {
        // Use the authenticated account, not identity or scope claims in chat.
        $role = $user->role_label;
        $scope = mb_substr($user->scopeLabel(), 0, 180);

        return $filipino
            ? 'Ang role ng naka-sign in mong account ay '.$role.'. Ang saklaw ng opisina mo ay '.$scope.'. Ito ang nakatakdang saklaw ng account, hindi isang kasalukuyang filter sa pahina.'
            : 'Your signed-in role is '.$role.'. Your office scope is '.$scope.'. This is your account assignment, not a current page filter.';
    }

    private function awareness(bool $filipino): string
    {
        return $filipino
            ? 'Ako ang AgriLGU Assistant, isang software helper; wala akong kamalayan o sariling pag-iisip tulad ng tao. Ginagamit ko ang sinuring gabay ng sistema, page context at pinapayagang follow-up references. Ang “Thinking” ay palatandaan lang na pinoproseso ang request. Hindi ko iniimbak ang buong chat o binabasa ang server directories.'
            : 'I’m the AgriLGU Assistant, a software helper, not a conscious person. I use reviewed system guidance, page context and permitted follow-up references. “Thinking” means a request is being processed. I do not store the full conversation or read server directories.';
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
