<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\EventParticipant;

final class CommunityEventPdfService
{
    private const PAGE_WIDTH = 595.28;
    private const PAGE_HEIGHT = 841.89;
    private const MARGIN = 44.0;

    /** @var array<int, list<string>> */
    private array $pages = [];
    private int $pageIndex = -1;
    private float $cursorY = 0.0;
    private string $eventTitle = 'Community event';

    /** @param EventParticipant[] $participants
     *  @param array<string, array<string, mixed>|null> $aiSections
     */
    public function render(Event $event, array $participants, array $aiSections): string
    {
        $this->pages = [];
        $this->pageIndex = -1;
        $this->eventTitle = $this->sanitize((string) $event->getTitle()) !== ''
            ? $this->sanitize((string) $event->getTitle())
            : 'Community event';

        $this->startPage(true);
        $this->drawHero($event, $participants);
        $this->drawMetaCards($event, $participants);
        $this->drawSectionTitle('Vue d ensemble');
        $this->drawParagraph(
            $this->sanitizeMultiline((string) $event->getDescription()) !== ''
                ? $this->sanitizeMultiline((string) $event->getDescription())
                : 'Aucune description detaillee n a ete renseignee pour cet evenement.',
        );
        $this->drawSpacer(10);

        $this->drawSectionTitle('Assistant IA');
        $this->drawAiPanel('Resume', $aiSections['summary'] ?? null);
        $this->drawSpacer(8);
        $this->drawAiPanel('Promo', $aiSections['promo'] ?? null);
        $this->drawSpacer(8);
        $this->drawAiPanel('Checklist', $aiSections['checklist'] ?? null);
        $this->drawSpacer(10);

        $this->drawSectionTitle('Participants');
        $this->drawParticipantsTable($event, $participants);

        return $this->compilePdf();
    }

    /** @param EventParticipant[] $participants */
    private function drawHero(Event $event, array $participants): void
    {
        $this->drawFilledRect(0.0, self::PAGE_HEIGHT - 154.0, self::PAGE_WIDTH, 154.0, [22, 38, 71]);
        $this->drawFilledRect(self::MARGIN, self::PAGE_HEIGHT - 124.0, 136.0, 4.0, [104, 140, 255]);
        $this->drawText(self::MARGIN, self::PAGE_HEIGHT - 40.0, 'COMMUNITY EVENT REPORT', 10.5, true, [205, 217, 255]);
        $this->drawText(self::MARGIN, self::PAGE_HEIGHT - 74.0, $this->fitText($this->eventTitle, self::PAGE_WIDTH - (self::MARGIN * 2), 24.0, true), 24.0, true, [255, 255, 255]);
        $this->drawText(
            self::MARGIN,
            self::PAGE_HEIGHT - 98.0,
            sprintf(
                'Organisateur: %s | Date: %s | Participants: %d',
                $this->sanitize(trim((string) $event->getCreatedBy()?->getFullName())) !== '' ? $this->sanitize(trim((string) $event->getCreatedBy()?->getFullName())) : 'Inconnu',
                $event->getEventDate()?->format('d/m/Y H:i') ?? 'A confirmer',
                count($participants),
            ),
            11.0,
            false,
            [226, 233, 255],
        );
        $this->drawText(
            self::MARGIN,
            self::PAGE_HEIGHT - 120.0,
            $this->fitText(
                'Export genere le '.date('d/m/Y a H:i').' pour le suivi de l evenement et du roster inscrit.',
                self::PAGE_WIDTH - (self::MARGIN * 2),
                10.0,
                false,
            ),
            10.0,
            false,
            [187, 200, 235],
        );

        $this->cursorY = self::PAGE_HEIGHT - 182.0;
    }

    /** @param EventParticipant[] $participants */
    private function drawMetaCards(Event $event, array $participants): void
    {
        $cardWidth = (self::PAGE_WIDTH - (self::MARGIN * 2) - 12.0) / 2.0;
        $cardHeight = 68.0;
        $top = $this->cursorY;
        $cards = [
            ['Date', $event->getEventDate()?->format('d/m/Y') ?? 'A confirmer'],
            ['Heure', $event->getEventDate()?->format('H:i') ?? 'A confirmer'],
            ['Capacite', $event->getCapacity() > 0 ? (string) $event->getCapacity().' places' : 'Illimitee'],
            ['Participants', count($participants).' inscrits'],
        ];

        foreach ($cards as $index => $card) {
            $column = $index % 2;
            $row = intdiv($index, 2);
            $x = self::MARGIN + ($column * ($cardWidth + 12.0));
            $yTop = $top - ($row * ($cardHeight + 12.0));
            $this->drawPanel($x, $yTop, $cardWidth, $cardHeight, [246, 249, 255], [220, 227, 241]);
            $this->drawText($x + 14.0, $yTop - 20.0, (string) $card[0], 9.5, true, [102, 116, 150]);
            $this->drawText($x + 14.0, $yTop - 43.0, $this->fitText((string) $card[1], $cardWidth - 28.0, 16.0, true), 16.0, true, [31, 48, 89]);
        }

        $this->cursorY = $top - ((2 * $cardHeight) + 26.0);
    }

    private function drawSectionTitle(string $title): void
    {
        if ($this->needsPage(28.0)) {
            $this->startPage();
        }

        $this->drawText(self::MARGIN, $this->cursorY, $title, 15.0, true, [26, 43, 76]);
        $lineY = $this->cursorY - 10.0;
        $this->append(sprintf('q %.3F %.3F %.3F RG 1 w %.2F %.2F m %.2F %.2F l S Q', 0.42, 0.55, 0.94, self::MARGIN, $lineY, self::PAGE_WIDTH - self::MARGIN, $lineY));
        $this->cursorY -= 24.0;
    }

    private function drawParagraph(string $text, float $fontSize = 11.4, array $color = [51, 66, 97]): void
    {
        $paragraphs = preg_split('/\R+/u', $this->sanitizeMultiline($text)) ?: [];
        $lineHeight = $fontSize * 1.45;

        foreach ($paragraphs as $paragraphIndex => $paragraph) {
            $lines = $this->wrapText($paragraph, self::PAGE_WIDTH - (self::MARGIN * 2), $fontSize, false);
            if ($lines === []) {
                continue;
            }

            foreach ($lines as $line) {
                if ($this->needsPage($lineHeight + 4.0)) {
                    $this->startPage();
                }

                $this->drawText(self::MARGIN, $this->cursorY, $line, $fontSize, false, $color);
                $this->cursorY -= $lineHeight;
            }

            if ($paragraphIndex < count($paragraphs) - 1) {
                $this->cursorY -= 4.0;
            }
        }
    }

    /** @param array<string, mixed>|null $result */
    private function drawAiPanel(string $title, ?array $result): void
    {
        $body = trim((string) ($result['content'] ?? ''));
        $error = (bool) ($result['error'] ?? false);
        $errorMessage = trim((string) ($result['error_message'] ?? ''));
        $hint = trim((string) ($result['source_hint'] ?? ''));
        $sourceLabel = trim((string) ($result['source_label'] ?? 'En attente'));
        $model = trim((string) ($result['model'] ?? ''));
        $source = $model !== '' ? $sourceLabel.' - '.$model : $sourceLabel;

        if ($body === '') {
            $body = $errorMessage !== '' ? $errorMessage : 'Aucun texte n a encore ete genere pour ce bloc.';
        }

        $panelWidth = self::PAGE_WIDTH - (self::MARGIN * 2);
        $panelPadding = 14.0;
        $bodyLines = $this->wrapText($body, $panelWidth - ($panelPadding * 2), 11.0, false);
        $hintLines = $hint !== '' ? $this->wrapText($hint, $panelWidth - ($panelPadding * 2), 9.2, false) : [];
        $panelHeight = 22.0 + 18.0 + (count($bodyLines) * 15.0) + ($hintLines === [] ? 0.0 : (10.0 + (count($hintLines) * 12.0))) + 16.0;

        if ($this->needsPage($panelHeight + 4.0)) {
            $this->startPage();
        }

        $fill = $error ? [255, 244, 244] : ((bool) ($result['used_provider'] ?? false) ? [241, 249, 242] : [246, 248, 252]);
        $stroke = $error ? [238, 193, 193] : ((bool) ($result['used_provider'] ?? false) ? [186, 220, 188] : [221, 227, 238]);
        $sourceColor = $error ? [176, 70, 70] : ((bool) ($result['used_provider'] ?? false) ? [46, 125, 50] : [102, 116, 150]);
        $top = $this->cursorY;

        $this->drawPanel(self::MARGIN, $top, $panelWidth, $panelHeight, $fill, $stroke);
        $textY = $top - 18.0;
        $this->drawText(self::MARGIN + $panelPadding, $textY, $title, 12.5, true, [31, 48, 89]);
        $this->drawText(self::MARGIN + $panelPadding, $textY - 16.0, $this->fitText($source, $panelWidth - ($panelPadding * 2), 9.2, false), 9.2, false, $sourceColor);

        $currentY = $textY - 34.0;
        foreach ($bodyLines as $line) {
            $this->drawText(self::MARGIN + $panelPadding, $currentY, $line, 11.0, false, [51, 66, 97]);
            $currentY -= 15.0;
        }

        if ($hintLines !== []) {
            $currentY -= 4.0;
            foreach ($hintLines as $line) {
                $this->drawText(self::MARGIN + $panelPadding, $currentY, $line, 9.2, false, [116, 128, 157]);
                $currentY -= 12.0;
            }
        }

        $this->cursorY = $top - $panelHeight - 2.0;
    }

    /** @param EventParticipant[] $participants */
    private function drawParticipantsTable(Event $event, array $participants): void
    {
        if ($participants === []) {
            $this->drawParagraph('Aucun participant pour le moment.', 11.0, [110, 123, 154]);

            return;
        }

        $this->drawParticipantTableHeader();

        foreach ($participants as $index => $participant) {
            if ($this->needsPage(30.0)) {
                $this->startPage();
                $this->drawSectionTitle('Participants');
                $this->drawParticipantTableHeader();
            }

            $top = $this->cursorY;
            $fill = $index % 2 === 0 ? [252, 253, 255] : [246, 249, 255];
            $stroke = [225, 231, 241];
            $rowHeight = 26.0;
            $x = self::MARGIN;
            $widths = [34.0, 156.0, 221.0, 96.0];
            $values = [
                (string) ($index + 1),
                $this->participantLabel($participant),
                $this->sanitize((string) $participant->getUser()?->getEmail()),
                $participant->getUser()?->getId() === $event->getCreatedBy()?->getId() ? 'Organisateur' : 'Participant',
            ];

            $this->drawPanel($x, $top, array_sum($widths), $rowHeight, $fill, $stroke);

            foreach ($widths as $columnIndex => $width) {
                $value = $this->fitText($values[$columnIndex], $width - 12.0, 10.0, $columnIndex === 0 || $columnIndex === 3);
                $this->drawText($x + 6.0, $top - 16.0, $value, 10.0, $columnIndex === 0 || $columnIndex === 3, [52, 66, 97]);
                $x += $width;

                if ($columnIndex < count($widths) - 1) {
                    $this->append(sprintf('q %.3F %.3F %.3F RG 0.6 w %.2F %.2F m %.2F %.2F l S Q', 0.88, 0.91, 0.95, $x, $top - $rowHeight, $x, $top));
                }
            }

            $this->cursorY -= ($rowHeight + 6.0);
        }
    }

    private function drawParticipantTableHeader(): void
    {
        if ($this->needsPage(32.0)) {
            $this->startPage();
        }

        $top = $this->cursorY;
        $widths = [34.0, 156.0, 221.0, 96.0];
        $labels = ['#', 'Nom complet', 'Email', 'Role'];
        $x = self::MARGIN;

        $this->drawPanel($x, $top, array_sum($widths), 24.0, [30, 47, 87], [30, 47, 87]);

        foreach ($widths as $index => $width) {
            $this->drawText($x + 6.0, $top - 15.5, $labels[$index], 9.4, true, [255, 255, 255]);
            $x += $width;

            if ($index < count($widths) - 1) {
                $this->append(sprintf('q %.3F %.3F %.3F RG 0.6 w %.2F %.2F m %.2F %.2F l S Q', 0.34, 0.42, 0.66, $x, $top - 24.0, $x, $top));
            }
        }

        $this->cursorY -= 30.0;
    }

    private function startPage(bool $firstPage = false): void
    {
        $this->pages[] = [];
        $this->pageIndex = count($this->pages) - 1;

        if ($firstPage) {
            $this->cursorY = self::PAGE_HEIGHT - self::MARGIN;

            return;
        }

        $this->drawFilledRect(0.0, self::PAGE_HEIGHT - 58.0, self::PAGE_WIDTH, 58.0, [27, 43, 79]);
        $this->drawText(self::MARGIN, self::PAGE_HEIGHT - 22.0, 'COMMUNITY EVENT REPORT', 9.2, true, [198, 209, 243]);
        $this->drawText(self::MARGIN, self::PAGE_HEIGHT - 43.0, $this->fitText($this->eventTitle, self::PAGE_WIDTH - (self::MARGIN * 2), 17.0, true), 17.0, true, [255, 255, 255]);
        $this->cursorY = self::PAGE_HEIGHT - 84.0;
    }

    private function drawPanel(float $x, float $top, float $width, float $height, array $fill, array $stroke): void
    {
        $this->append(sprintf(
            'q %s rg %s RG 0.8 w %.2F %.2F %.2F %.2F re B Q',
            $this->rgb($fill),
            $this->rgb($stroke),
            $x,
            $top - $height,
            $width,
            $height,
        ));
    }

    private function drawFilledRect(float $x, float $y, float $width, float $height, array $fill): void
    {
        $this->append(sprintf('q %s rg %.2F %.2F %.2F %.2F re f Q', $this->rgb($fill), $x, $y, $width, $height));
    }

    private function drawText(float $x, float $y, string $text, float $fontSize, bool $bold, array $color): void
    {
        $encoded = $this->encode($text);

        if ($encoded === '') {
            return;
        }

        $this->append(sprintf(
            'BT /%s %.2F Tf %s rg 1 0 0 1 %.2F %.2F Tm (%s) Tj ET',
            $bold ? 'F2' : 'F1',
            $fontSize,
            $this->rgb($color),
            $x,
            $y,
            $this->escapePdfString($encoded),
        ));
    }

    private function drawSpacer(float $amount): void
    {
        $this->cursorY -= $amount;
    }

    private function needsPage(float $height): bool
    {
        return ($this->cursorY - $height) < self::MARGIN;
    }

    private function append(string $command): void
    {
        $this->pages[$this->pageIndex][] = $command;
    }

    private function compilePdf(): string
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];

        $kids = [];
        $nextId = 5;

        foreach ($this->pages as $pageCommands) {
            $pageId = $nextId++;
            $contentId = $nextId++;
            $stream = implode("\n", $pageCommands);
            $kids[] = $pageId.' 0 R';
            $objects[$pageId] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                self::PAGE_WIDTH,
                self::PAGE_HEIGHT,
                $contentId,
            );
            $objects[$contentId] = sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($stream), $stream);
        }

        $objects[2] = '<< /Type /Pages /Count '.count($kids).' /Kids ['.implode(' ', $kids).'] >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0 => 0];
        $maxId = max(array_keys($objects));

        for ($id = 1; $id <= $maxId; ++$id) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".($objects[$id] ?? '<< >>')."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= 'xref' . "\n";
        $pdf .= '0 '.($maxId + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($id = 1; $id <= $maxId; ++$id) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        }

        $pdf .= 'trailer' . "\n";
        $pdf .= '<< /Size '.($maxId + 1).' /Root 1 0 R >>' . "\n";
        $pdf .= 'startxref' . "\n";
        $pdf .= $xrefOffset."\n%%EOF";

        return $pdf;
    }

    /** @return string[] */
    private function wrapText(string $text, float $width, float $fontSize, bool $bold): array
    {
        $text = $this->sanitizeMultiline($text);

        if ($text === '') {
            return [];
        }

        $words = preg_split('/\s+/u', $text) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;

            if ($current !== '' && $this->estimateWidth($candidate, $fontSize, $bold) > $width) {
                $lines[] = $current;

                if ($this->estimateWidth($word, $fontSize, $bold) > $width) {
                    $pieces = $this->splitLongWord($word, $width, $fontSize, $bold);
                    $current = (string) array_pop($pieces);
                    array_push($lines, ...$pieces);
                } else {
                    $current = $word;
                }

                continue;
            }

            if ($current === '' && $this->estimateWidth($candidate, $fontSize, $bold) > $width) {
                $pieces = $this->splitLongWord($candidate, $width, $fontSize, $bold);
                $current = (string) array_pop($pieces);
                array_push($lines, ...$pieces);

                continue;
            }

            $current = $candidate;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return array_values(array_filter(array_map(fn (string $line): string => trim($line), $lines), static fn (string $line): bool => $line !== ''));
    }

    /** @return string[] */
    private function splitLongWord(string $word, float $width, float $fontSize, bool $bold): array
    {
        $pieces = [];
        $current = '';

        foreach (mb_str_split($word) as $character) {
            $candidate = $current.$character;

            if ($current !== '' && $this->estimateWidth($candidate, $fontSize, $bold) > $width) {
                $pieces[] = $current;
                $current = $character;

                continue;
            }

            $current = $candidate;
        }

        if ($current !== '') {
            $pieces[] = $current;
        }

        return $pieces;
    }

    private function fitText(string $text, float $width, float $fontSize, bool $bold): string
    {
        $text = $this->sanitize($text);

        if ($text === '') {
            return '';
        }

        if ($this->estimateWidth($text, $fontSize, $bold) <= $width) {
            return $text;
        }

        $truncated = $text;
        while (mb_strlen($truncated) > 1 && $this->estimateWidth($truncated.'...', $fontSize, $bold) > $width) {
            $truncated = rtrim(mb_substr($truncated, 0, -1));
        }

        return $truncated.'...';
    }

    private function estimateWidth(string $text, float $fontSize, bool $bold): float
    {
        $encoded = $this->encode($text);
        $factor = $bold ? 1.02 : 1.0;
        $width = 0.0;

        foreach (str_split($encoded) as $character) {
            if ($character === ' ') {
                $width += 0.28;

                continue;
            }

            if (preg_match('/[ilI\.,!:\'\|]/', $character) === 1) {
                $width += 0.24;

                continue;
            }

            if (preg_match('/[A-Z0-9]/', $character) === 1) {
                $width += 0.62 * $factor;

                continue;
            }

            $width += 0.52 * $factor;
        }

        return $width * $fontSize;
    }

    private function participantLabel(EventParticipant $participant): string
    {
        $name = trim((string) $participant->getUser()?->getFullName());

        return $this->sanitize($name !== '' ? $name : 'Participant');
    }

    private function sanitize(string $text): string
    {
        $text = strip_tags($text);
        $text = str_replace(["\r\n", "\r"], ' ', $text);
        $text = (string) preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }

    private function sanitizeMultiline(string $text): string
    {
        $text = strip_tags($text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = (string) preg_replace('/[ \t]+/u', ' ', $text);
        $text = (string) preg_replace('/\n{3,}/u', "\n\n", $text);

        return trim($text);
    }

    private function encode(string $text): string
    {
        $text = $this->sanitizeMultiline($text);
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);

        return $encoded === false ? preg_replace('/[^\x20-\x7E]/', '?', $text) ?? '' : $encoded;
    }

    private function escapePdfString(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function rgb(array $color): string
    {
        return sprintf('%.3F %.3F %.3F', ($color[0] ?? 0) / 255, ($color[1] ?? 0) / 255, ($color[2] ?? 0) / 255);
    }
}