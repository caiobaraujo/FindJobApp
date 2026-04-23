<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class ResumeTextExtractor
{
    private const MAX_TEXT_LENGTH = 50000;

    public function extract(UploadedFile $uploadedFile): ?string
    {
        return match (strtolower($uploadedFile->getClientOriginalExtension())) {
            'txt' => $this->extractTextFile($uploadedFile),
            'pdf' => $this->extractPdfFile($uploadedFile),
            'docx' => $this->extractDocxFile($uploadedFile),
            'doc' => null,
            default => null,
        };
    }

    private function extractTextFile(UploadedFile $uploadedFile): ?string
    {
        $contents = file_get_contents($uploadedFile->getRealPath());

        if ($contents === false) {
            return null;
        }

        return $this->normalizeText($contents);
    }

    private function extractPdfFile(UploadedFile $uploadedFile): ?string
    {
        try {
            $process = new Process([
                'pdftotext',
                '-layout',
                '-enc',
                'UTF-8',
                $uploadedFile->getRealPath(),
                '-',
            ]);
            $process->setTimeout(10);
            $process->run();
        } catch (Throwable) {
            return null;
        }

        if (! $process->isSuccessful()) {
            return null;
        }

        return $this->normalizeText($process->getOutput());
    }

    private function extractDocxFile(UploadedFile $uploadedFile): ?string
    {
        $archive = new ZipArchive();

        if ($archive->open($uploadedFile->getRealPath()) !== true) {
            return null;
        }

        try {
            $textParts = $this->docxTextParts($archive);
        } finally {
            $archive->close();
        }

        if ($textParts === []) {
            return null;
        }

        return $this->normalizeText(implode("\n", $textParts));
    }

    /**
     * @return list<string>
     */
    private function docxTextParts(ZipArchive $archive): array
    {
        $textParts = [];

        for ($index = 0; $index < $archive->numFiles; $index++) {
            $entryName = $archive->getNameIndex($index);

            if (! is_string($entryName) || ! $this->isDocxDocumentEntry($entryName)) {
                continue;
            }

            $contents = $archive->getFromName($entryName);

            if (! is_string($contents)) {
                continue;
            }

            $text = $this->textFromDocxXml($contents);

            if ($text !== null) {
                $textParts[] = $text;
            }
        }

        return $textParts;
    }

    private function isDocxDocumentEntry(string $entryName): bool
    {
        if ($entryName === 'word/document.xml') {
            return true;
        }

        if (preg_match('/^word\/header\d+\.xml$/', $entryName) === 1) {
            return true;
        }

        return preg_match('/^word\/footer\d+\.xml$/', $entryName) === 1;
    }

    private function textFromDocxXml(string $xml): ?string
    {
        $xml = str_replace(['</w:p>', '</w:tr>'], "\n", $xml);
        $xml = str_replace(['<w:tab/>', '<w:br/>', '<w:cr/>'], ' ', $xml);
        $text = strip_tags($xml);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return $this->normalizeText($text);
    }

    private function normalizeText(string $text): ?string
    {
        $text = str_replace("\0", '', $text);
        $text = preg_replace("/\r\n?/", "\n", $text) ?? '';
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? '';
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? '';
        $text = trim($text);

        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, self::MAX_TEXT_LENGTH);
    }
}
