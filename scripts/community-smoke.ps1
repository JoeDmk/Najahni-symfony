$ErrorActionPreference = 'Stop'

$base = 'http://127.0.0.1:8088'
$regexOptions = [System.Text.RegularExpressions.RegexOptions]::IgnoreCase -bor [System.Text.RegularExpressions.RegexOptions]::Singleline

function Get-MatchValue([string]$text, [string]$pattern) {
    $match = [System.Text.RegularExpressions.Regex]::Match($text, $pattern, $regexOptions)
    if (-not $match.Success) {
        throw "Pattern not found: $pattern"
    }

    return $match.Groups[1].Value
}

function Try-GetMatchValue([string]$text, [string]$pattern) {
    $match = [System.Text.RegularExpressions.Regex]::Match($text, $pattern, $regexOptions)
    if (-not $match.Success) {
        return $null
    }

    return $match.Groups[1].Value
}

function Decode-Html([string]$text) {
    return [System.Net.WebUtility]::HtmlDecode($text)
}

function Normalize-Text([string]$text) {
    $decoded = Decode-Html $text
    $withoutTags = $decoded -replace '<[^>]+>', ' '

    return ($withoutTags -replace '\s+', ' ').Trim()
}

function New-ValidationUser([string]$label, [string]$phone) {
    $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $stamp = Get-Date -Format 'yyyyMMddHHmmssfff'
    $email = ('community.validate.{0}.{1}@example.com' -f $label, $stamp)
    $firstName = (Get-Culture).TextInfo.ToTitleCase($label)
    $form = @{
        firstname = $firstName
        lastname = 'Smoke'
        email = $email
        phone = $phone
        role = 'ENTREPRENEUR'
        password = 'ValidPass1'
        confirm_password = 'ValidPass1'
        _recaptcha_token = ''
    }

    $register = Invoke-WebRequest -UseBasicParsing -Uri "$base/register" -Method Post -Body $form -WebSession $session -MaximumRedirection 0 -ErrorAction SilentlyContinue
    if (-not $register -or [int]$register.StatusCode -ne 302) {
        throw "Registration failed for $email"
    }

    $skip = Invoke-WebRequest -UseBasicParsing -Uri "$base/verify-email" -Method Post -Body @{ action = 'skip' } -WebSession $session -MaximumRedirection 0 -ErrorAction SilentlyContinue
    if (-not $skip -or [int]$skip.StatusCode -ne 302) {
        throw "Skip verification failed for $email"
    }

    return [pscustomobject]@{
        Label = $label
        Email = $email
        Password = 'ValidPass1'
        Session = $session
    }
}

function Login-ValidationUser($account) {
    $loginPage = Invoke-WebRequest -UseBasicParsing -Uri "$base/login" -WebSession $account.Session
    $csrf = Get-MatchValue $loginPage.Content 'name="_csrf_token" value="([^"]+)"'
    $headers = @{ Origin = $base; Referer = "$base/login" }
    $login = Invoke-WebRequest -UseBasicParsing -Uri "$base/login" -Method Post -Body @{
        _username = $account.Email
        _password = $account.Password
        _csrf_token = $csrf
        _recaptcha_token = ''
        _remember_me = 'on'
    } -Headers $headers -WebSession $account.Session -MaximumRedirection 0 -ErrorAction SilentlyContinue

    if (-not $login -or [int]$login.StatusCode -ne 302) {
        throw "Login failed for $($account.Email)"
    }

    $groupsPage = Invoke-WebRequest -UseBasicParsing -Uri "$base/community/groups" -WebSession $account.Session
    if ([int]$groupsPage.StatusCode -ne 200) {
        throw "Authenticated groups page failed for $($account.Email)"
    }
}

$results = [ordered]@{
    users = [ordered]@{}
    group = [ordered]@{}
    thread = [ordered]@{}
    post = [ordered]@{}
    event = [ordered]@{}
}

Write-Output 'STAGE=users'
$owner = New-ValidationUser 'owner' '+21620000011'
$member = New-ValidationUser 'member' '+21620000012'
Login-ValidationUser $owner
Login-ValidationUser $member
$results.users.owner = $owner.Email
$results.users.member = $member.Email

Write-Output 'STAGE=group'
$stamp = Get-Date -Format 'yyyyMMddHHmmss'
$groupName = "Validation Private Group $stamp"
$groupDescription = 'Private area to validate join requests, admin approval, and thread access.'
$groupCreate = Invoke-WebRequest -UseBasicParsing -Uri "$base/community/groups/new" -Method Post -Body @{ name = $groupName; description = $groupDescription; is_private = '1' } -WebSession $owner.Session -MaximumRedirection 0 -ErrorAction SilentlyContinue
$groupLocation = $groupCreate.Headers.Location
$groupId = [int](Get-MatchValue $groupLocation '/community/groups/(\d+)')
$memberBeforeJoin = Invoke-WebRequest -UseBasicParsing -Uri "$base/community/groups/$groupId" -WebSession $member.Session
$results.group.id = $groupId
$results.group.locked_before_approval = ($memberBeforeJoin.Content -match 'faut une approbation avant d.acceder au contenu' -or $memberBeforeJoin.Content -match 'contenu reste verrouille')

$joinRequest = Invoke-WebRequest -UseBasicParsing -Uri "$base/community/groups/$groupId/join" -Method Post -WebSession $member.Session -MaximumRedirection 0 -ErrorAction SilentlyContinue
if (-not $joinRequest -or [int]$joinRequest.StatusCode -ne 302) {
    throw 'Group join request failed.'
}

$ownerGroupPage = Invoke-WebRequest -UseBasicParsing -Uri "$base/community/groups/$groupId" -WebSession $owner.Session
$requestId = [int](Get-MatchValue $ownerGroupPage.Content ('/community/groups/{0}/requests/(\d+)/approve' -f $groupId))
$approve = Invoke-WebRequest -UseBasicParsing -Uri "$base/community/groups/$groupId/requests/$requestId/approve" -Method Post -WebSession $owner.Session -MaximumRedirection 0 -ErrorAction SilentlyContinue
if (-not $approve -or [int]$approve.StatusCode -ne 302) {
    throw 'Group approval failed.'
}

$memberAfterApprove = Invoke-WebRequest -UseBasicParsing -Uri "$base/community/groups/$groupId" -WebSession $member.Session
$results.group.request_id = $requestId
$results.group.access_after_approval = ($memberAfterApprove.Content -match 'Nouvelle discussion' -and $memberAfterApprove.Content -notmatch 'contenu reste verrouille')

Write-Output 'STAGE=thread'
$threadTitle = "Validation Thread $stamp"
$threadContent = 'I need concrete ideas to keep this private group useful, structured, and easy to follow.'
$threadCreate = Invoke-WebRequest -UseBasicParsing -Uri "$base/community/groups/$groupId/threads/new" -Method Post -Body @{ title = $threadTitle; content = $threadContent } -WebSession $member.Session -MaximumRedirection 0 -ErrorAction SilentlyContinue
$threadLocation = $threadCreate.Headers.Location
$threadId = [int](Get-MatchValue $threadLocation '/community/threads/(\d+)')

Invoke-WebRequest -UseBasicParsing -Uri "$base/community/threads/$threadId/comment" -Method Post -Body @{ content = 'I suggest a simple cadence: one clear question, two argued replies, then a short summary.' } -WebSession $owner.Session -MaximumRedirection 0 -ErrorAction SilentlyContinue | Out-Null
Invoke-WebRequest -UseBasicParsing -Uri "$base/community/threads/$threadId/comment" -Method Post -Body @{ content = 'That makes sense. We can also capture agreements and next steps at the end of each discussion.' } -WebSession $member.Session -MaximumRedirection 0 -ErrorAction SilentlyContinue | Out-Null
Invoke-WebRequest -UseBasicParsing -Uri "$base/community/threads/$threadId/summary" -Method Post -WebSession $owner.Session -MaximumRedirection 0 -ErrorAction SilentlyContinue | Out-Null
Invoke-WebRequest -UseBasicParsing -Uri "$base/community/threads/$threadId/reply-suggestions" -Method Post -WebSession $owner.Session -MaximumRedirection 0 -ErrorAction SilentlyContinue | Out-Null

$threadPage = Invoke-WebRequest -UseBasicParsing -Uri "$base/community/threads/$threadId" -WebSession $owner.Session
$threadSummaryRaw = Get-MatchValue $threadPage.Content 'text-uppercase text-muted fw-semibold mb-2">Resume</div>\s*<div>(.*?)</div>'
$suggestionMatches = [System.Text.RegularExpressions.Regex]::Matches($threadPage.Content, 'data-suggestion="([^"]+)"', $regexOptions)
$threadSuggestions = @($suggestionMatches | ForEach-Object { Normalize-Text $_.Groups[1].Value })

$results.thread.id = $threadId
$results.thread.thread_page_opens = ($threadPage.Content -match [System.Text.RegularExpressions.Regex]::Escape($threadTitle))
$results.thread.summary = Normalize-Text $threadSummaryRaw
$results.thread.suggestions = $threadSuggestions

Write-Output 'STAGE=post'
$postContent = "Bonjour a tous, nous preparons un atelier communautaire sur le mentorat et la presentation du projet $stamp."
Invoke-WebRequest -UseBasicParsing -Uri "$base/community/posts/new" -Method Post -Body @{ content = $postContent } -WebSession $owner.Session -MaximumRedirection 0 -ErrorAction SilentlyContinue | Out-Null

$postsPage = Invoke-WebRequest -UseBasicParsing -Uri "$base/community/posts" -WebSession $owner.Session
$postPattern = 'data-post-id="(\d+)"[\s\S]{0,5000}' + [System.Text.RegularExpressions.Regex]::Escape($postContent)
$postId = [int](Get-MatchValue $postsPage.Content $postPattern)
$translation = Invoke-RestMethod -UseBasicParsing -Uri "$base/community/posts/$postId/translate?target=en" -WebSession $owner.Session
$ajaxHeaders = @{ 'X-Requested-With' = 'XMLHttpRequest' }
$reaction = Invoke-RestMethod -UseBasicParsing -Uri "$base/community/posts/$postId/react" -Method Post -Body @{ reaction = 'LOVE' } -Headers $ajaxHeaders -WebSession $member.Session
$unreaction = Invoke-RestMethod -UseBasicParsing -Uri "$base/community/posts/$postId/react/remove" -Method Post -Headers $ajaxHeaders -WebSession $member.Session

$results.post.id = $postId
$results.post.translation_ok = [bool]$translation.ok
$results.post.translation_text = $translation.text
$results.post.reaction_after_add = $reaction.myReaction
$results.post.reaction_count_after_add = $reaction.reactionsCount
$results.post.reaction_after_remove = $unreaction.myReaction
$results.post.reaction_count_after_remove = $unreaction.reactionsCount

Write-Output 'STAGE=event'
$eventTitle = "Validation Event $stamp"
$eventDescription = 'Hands-on meetup to test creation, weather, QR tickets, and AI-generated event text.'
$eventDate = (Get-Date).AddDays(2).Date.AddHours(18).ToString('yyyy-MM-ddTHH:mm')
$eventCreate = Invoke-WebRequest -UseBasicParsing -Uri "$base/community/events/new" -Method Post -Body @{ title = $eventTitle; description = $eventDescription; event_date = $eventDate; capacity = '5' } -WebSession $owner.Session -MaximumRedirection 0 -ErrorAction SilentlyContinue
$eventLocation = $eventCreate.Headers.Location
$eventId = [int](Get-MatchValue $eventLocation '/community/events/(\d+)')

Invoke-WebRequest -UseBasicParsing -Uri "$base/community/events/$eventId/join" -Method Post -WebSession $member.Session -MaximumRedirection 0 -ErrorAction SilentlyContinue | Out-Null
$memberEventPage = Invoke-WebRequest -UseBasicParsing -Uri "$base/community/events/$eventId" -WebSession $member.Session
$weatherSource = Try-GetMatchValue $memberEventPage.Content 'Source\s*:\s*([^<]+)</div>'
$weatherMessage = Try-GetMatchValue $memberEventPage.Content '<h5 class="fw-bold mb-3"><i class="bi bi-cloud-sun me-2"></i>Meteo</h5>[\s\S]*?<div class="text-muted">(.*?)</div>'
$ticketPayload = Get-MatchValue $memberEventPage.Content '<textarea class="form-control" rows="4" readonly>(.*?)</textarea>'

$eventModes = [ordered]@{}
foreach ($mode in @('summary', 'promo', 'checklist')) {
    Invoke-WebRequest -UseBasicParsing -Uri "$base/community/events/$eventId/ai" -Method Post -Body @{ mode = $mode } -WebSession $owner.Session -MaximumRedirection 0 -ErrorAction SilentlyContinue | Out-Null
    $ownerEventPage = Invoke-WebRequest -UseBasicParsing -Uri "$base/community/events/$eventId" -WebSession $owner.Session
    $aiOutput = Get-MatchValue $ownerEventPage.Content 'Assistant IA.*?<div style="white-space:pre-wrap">(.*?)</div>'
    $eventModes[$mode] = Normalize-Text $aiOutput
}

Invoke-WebRequest -UseBasicParsing -Uri "$base/community/events/$eventId/tickets/validate" -Method Post -Body @{ payload = $ticketPayload } -WebSession $owner.Session -MaximumRedirection 0 -ErrorAction SilentlyContinue | Out-Null
$validatedPage = Invoke-WebRequest -UseBasicParsing -Uri "$base/community/events/$eventId" -WebSession $owner.Session
$ticketMessage = Try-GetMatchValue $validatedPage.Content '<div class="fw-semibold mb-1">(Ticket valide\.|Ticket invalide ou modifie\.)</div>'

$results.event.id = $eventId
$results.event.weather_source = if ($weatherSource) { Normalize-Text $weatherSource } else { $null }
$results.event.weather_message = if ($weatherMessage) { Normalize-Text $weatherMessage } else { $null }
$results.event.ai = $eventModes
$results.event.ticket_validation = $ticketMessage
$results.event.ticket_payload_prefix = $ticketPayload.Substring(0, [Math]::Min(60, $ticketPayload.Length))

$results | ConvertTo-Json -Depth 6