<?php

use App\Services\ResumeTextExtractor;
use Illuminate\Http\UploadedFile;

it('extracts text from txt resume uploads', function (): void {
    $extractor = new ResumeTextExtractor();
    $resumeFile = UploadedFile::fake()->createWithContent('resume.txt', "Laravel Vue\nSQL AWS");

    expect($extractor->extract($resumeFile))->toBe("Laravel Vue\nSQL AWS");
});

it('extracts text from docx resume uploads', function (): void {
    $extractor = new ResumeTextExtractor();
    $resumeFile = uploadedDocxResume('resume.docx', 'Laravel Vue SQL AWS');

    expect($extractor->extract($resumeFile))->toBe('Laravel Vue SQL AWS');
});

it('extracts text from pdf resume uploads when local pdftotext is available', function (): void {
    if (! localPdftotextIsAvailable()) {
        $this->markTestSkipped('pdftotext is not available locally.');
    }

    $extractor = new ResumeTextExtractor();
    $resumeFile = UploadedFile::fake()->createWithContent('resume.pdf', minimalPdf('Laravel Vue Resume Text'));

    expect($extractor->extract($resumeFile))->toContain('Laravel Vue Resume Text');
});

it('returns null for pdf uploads that local tooling cannot extract', function (): void {
    $extractor = new ResumeTextExtractor();
    $resumeFile = UploadedFile::fake()->createWithContent('resume.pdf', 'not a valid pdf document');

    expect($extractor->extract($resumeFile))->toBeNull();
});

it('keeps legacy doc uploads as fallback only', function (): void {
    $extractor = new ResumeTextExtractor();
    $resumeFile = UploadedFile::fake()->createWithContent('resume.doc', 'Laravel Vue SQL AWS');

    expect($extractor->extract($resumeFile))->toBeNull();
});

function uploadedDocxResume(string $name, string $text): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'resume-docx-');
    $archive = new ZipArchive();
    $archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $archive->addFromString('[Content_Types].xml', docxContentTypesXml());
    $archive->addFromString('_rels/.rels', docxRelsXml());
    $archive->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>'.htmlspecialchars($text, ENT_XML1).'</w:t></w:r></w:p></w:body></w:document>');
    $archive->close();

    return new UploadedFile($path, $name, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);
}

function docxContentTypesXml(): string
{
    return '<?xml version="1.0" encoding="UTF-8"?>'
        .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        .'<Default Extension="xml" ContentType="application/xml"/>'
        .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
        .'</Types>';
}

function docxRelsXml(): string
{
    return '<?xml version="1.0" encoding="UTF-8"?>'
        .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
        .'</Relationships>';
}

function minimalPdf(string $text): string
{
    $stream = "BT\n/F1 24 Tf\n100 700 Td\n(".str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text).") Tj\nET";

    return "%PDF-1.1\n"
        ."1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        ."2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        ."3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /MediaBox [0 0 612 792] /Contents 5 0 R >>\nendobj\n"
        ."4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        ."5 0 obj\n<< /Length ".strlen($stream)." >>\nstream\n{$stream}\nendstream\nendobj\n"
        ."trailer\n<< /Root 1 0 R >>\n%%EOF\n";
}

function localPdftotextIsAvailable(): bool
{
    $process = new Symfony\Component\Process\Process(['pdftotext', '-v']);
    $process->run();

    return $process->isSuccessful();
}
