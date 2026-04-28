const APP_ENDPOINT = 'http://localhost:8000/job-leads/import/post';
const CSRF_ENDPOINT = 'http://localhost:8000/csrf-token';
const MAX_SCAN_CANDIDATES = 10;

const HIRING_TERMS = [
    'hiring',
    "we're hiring",
    'we are hiring',
    'looking for',
    'open role',
    'job opening',
    'opportunity',
    'vaga',
    'vagas',
    'contratando',
    'estamos contratando',
    'oportunidade',
];

const TECHNICAL_TERMS = [
    'php',
    'laravel',
    'vue',
    'vuejs',
    'vue.js',
    'python',
    'django',
    'backend',
    'frontend',
    'full stack',
    'remote',
    'remoto',
    'hybrid',
    'hibrido',
    'híbrido',
];

const NEGATIVE_TERMS = [
    'course',
    'curso',
    'bootcamp',
    'webinar',
    'hiring tips',
    'recruiter tips',
    'advice',
];

const elements = {
    companyName: document.getElementById('company-name'),
    contextText: document.getElementById('context-text'),
    jobTitle: document.getElementById('job-title'),
    jobUrl: document.getElementById('job-url'),
    manualPanel: document.getElementById('manual-panel'),
    pageUrl: document.getElementById('page-url'),
    scanButton: document.getElementById('scan-button'),
    scanPanel: document.getElementById('scan-panel'),
    scanResults: document.getElementById('scan-results'),
    sendButton: document.getElementById('send-button'),
    sendSelectedButton: document.getElementById('send-selected-button'),
    showManualButton: document.getElementById('show-manual-button'),
    showScanButton: document.getElementById('show-scan-button'),
    status: document.getElementById('status'),
};

let activePageUrl = '';
let activeTabId = null;
let scanCandidates = [];

initialize().catch((error) => {
    setStatus(error.message || 'Failed to initialize the extension.', 'error');
});

elements.showManualButton.addEventListener('click', () => showMode('manual'));
elements.showScanButton.addEventListener('click', () => showMode('scan'));
elements.sendButton.addEventListener('click', () => {
    void sendManualCapture();
});
elements.scanButton.addEventListener('click', () => {
    void scanVisiblePosts();
});
elements.sendSelectedButton.addEventListener('click', () => {
    void sendSelectedCandidates();
});

async function initialize() {
    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });

    if (!tab || typeof tab.id !== 'number') {
        throw new Error('No active tab is available.');
    }

    activeTabId = tab.id;

    const pageContext = await runInActiveTab(capturePageContext);

    activePageUrl = pageContext.pageUrl || tab.url || '';
    elements.pageUrl.textContent = activePageUrl || 'Unavailable';
    elements.contextText.value = pageContext.capturedText || '';

    if (pageContext.pageTitle) {
        elements.jobTitle.placeholder = pageContext.pageTitle;
    }

    renderCandidates([]);
}

async function sendManualCapture() {
    const contextText = elements.contextText.value.trim();

    if (contextText === '') {
        setStatus('Captured text is required.', 'error');

        return;
    }

    const payload = {
        source_platform: sourcePlatformFor(activePageUrl),
        source_post_url: activePageUrl,
        source_context_text: contextText,
        source_url: nullableValue(elements.jobUrl.value),
        job_title: nullableValue(elements.jobTitle.value),
        company_name: nullableValue(elements.companyName.value),
    };

    setBusy(elements.sendButton, true, 'Sending...');
    setStatus('Sending manual capture to FindJobApp...', null);

    try {
        const result = await sendPayload(payload);

        if (result.status === 'created') {
            setStatus(result.message || 'Job lead created in FindJobApp.', 'success');

            return;
        }

        if (result.status === 'duplicate') {
            setStatus(result.message || 'This post was already imported.', 'error');

            return;
        }

        setStatus(result.message || 'Request failed.', 'error');
    } catch (error) {
        setStatus(
            error instanceof Error ? error.message : 'Could not reach FindJobApp at http://localhost:8000.',
            'error',
        );
    } finally {
        setBusy(elements.sendButton, false, 'Send to FindJobApp');
    }
}

async function scanVisiblePosts() {
    setBusy(elements.scanButton, true, 'Scanning...');
    setStatus('Scanning visible current-page content...', null);

    try {
        const pageScanResult = await runInActiveTab(scanVisiblePostCandidates);
        scanCandidates = scoreCandidates(pageScanResult.blocks || []);
        renderCandidates(scanCandidates);

        if (scanCandidates.length === 0) {
            setStatus('No likely visible hiring posts were found with the current heuristic.', 'error');

            return;
        }

        setStatus(`Found ${scanCandidates.length} candidate posts. Review them before sending.`, 'success');
    } catch (error) {
        renderCandidates([]);
        setStatus(
            error instanceof Error ? error.message : 'Could not scan visible page content.',
            'error',
        );
    } finally {
        setBusy(elements.scanButton, false, 'Scan visible posts');
    }
}

async function sendSelectedCandidates() {
    const selectedCandidates = selectedScanCandidates();

    if (selectedCandidates.length === 0) {
        setStatus('Select at least one scanned candidate before sending.', 'error');

        return;
    }

    setBusy(elements.sendSelectedButton, true, 'Sending...');
    setStatus(`Sending ${selectedCandidates.length} selected candidates...`, null);

    const summary = {
        created: 0,
        duplicates: 0,
        failed: 0,
    };

    try {
        for (const candidate of selectedCandidates) {
            const result = await sendPayload({
                source_platform: sourcePlatformFor(activePageUrl),
                source_post_url: activePageUrl,
                source_context_text: candidate.text,
                source_url: extractedHttpUrl(candidate.text),
                job_title: null,
                company_name: null,
            });

            if (result.status === 'created') {
                summary.created++;

                continue;
            }

            if (result.status === 'duplicate') {
                summary.duplicates++;

                continue;
            }

            summary.failed++;
        }

        setStatus(
            `Sent ${summary.created}, duplicates ${summary.duplicates}, failures ${summary.failed}.`,
            summary.failed > 0 ? 'error' : 'success',
        );
    } catch (error) {
        setStatus(
            error instanceof Error ? error.message : 'Could not reach FindJobApp at http://localhost:8000.',
            'error',
        );
    } finally {
        setBusy(elements.sendSelectedButton, false, 'Send selected to FindJobApp');
    }
}

async function sendPayload(payload) {
    const csrfToken = await fetchCsrfToken();
    const response = await fetch(APP_ENDPOINT, {
        method: 'POST',
        credentials: 'include',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(payload),
    });

    const responseData = await safeJson(response);

    if (response.status === 201) {
        return {
            status: 'created',
            message: responseData.message || 'Job lead created in FindJobApp.',
        };
    }

    if (response.status === 409) {
        return {
            status: 'duplicate',
            message: responseData.message || 'This post was already imported.',
        };
    }

    if (response.status === 401) {
        throw new Error('Log into FindJobApp locally first, then try again.');
    }

    if (response.status === 419) {
        throw new Error('The CSRF token was rejected. Refresh FindJobApp locally and try again.');
    }

    if (response.status === 422) {
        return {
            status: 'validation_error',
            message: validationMessage(responseData) || 'The request was rejected by FindJobApp validation.',
        };
    }

    return {
        status: 'failed',
        message: responseData.message || `Request failed with HTTP ${response.status}.`,
    };
}

function showMode(mode) {
    const manualMode = mode === 'manual';

    elements.manualPanel.classList.toggle('is-visible', manualMode);
    elements.scanPanel.classList.toggle('is-visible', !manualMode);
    elements.showManualButton.classList.toggle('is-active', manualMode);
    elements.showScanButton.classList.toggle('is-active', !manualMode);
}

function renderCandidates(candidates) {
    elements.scanResults.innerHTML = '';

    if (candidates.length === 0) {
        const emptyState = document.createElement('div');
        emptyState.className = 'empty-state';
        emptyState.textContent = 'No scanned candidates yet. Click "Scan visible posts" to inspect the visible current page.';
        elements.scanResults.appendChild(emptyState);

        return;
    }

    for (const candidate of candidates) {
        const card = document.createElement('label');
        card.className = 'candidate';

        const header = document.createElement('div');
        header.className = 'candidate-header';

        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.checked = candidate.highConfidence;
        checkbox.dataset.candidateId = String(candidate.id);

        const body = document.createElement('div');

        const preview = document.createElement('p');
        preview.className = 'candidate-preview';
        preview.textContent = previewText(candidate.text);

        const meta = document.createElement('p');
        meta.className = 'candidate-meta';
        meta.textContent = `${candidate.highConfidence ? 'High' : 'Medium'} confidence · score ${candidate.score} · source ${activePageUrl}`;

        body.appendChild(preview);
        body.appendChild(meta);
        header.appendChild(checkbox);
        header.appendChild(body);
        card.appendChild(header);
        elements.scanResults.appendChild(card);
    }
}

function selectedScanCandidates() {
    const selectedIds = Array.from(
        elements.scanResults.querySelectorAll('input[type="checkbox"]:checked'),
        (checkbox) => Number(checkbox.dataset.candidateId),
    );

    return scanCandidates.filter((candidate) => selectedIds.includes(candidate.id));
}

async function runInActiveTab(func) {
    if (activeTabId === null) {
        throw new Error('No active tab is available.');
    }

    const [{ result }] = await chrome.scripting.executeScript({
        target: { tabId: activeTabId },
        func,
    });

    if (!result) {
        throw new Error('Could not read visible page content.');
    }

    return result;
}

function setBusy(button, isBusy, busyLabel) {
    button.disabled = isBusy;
    button.textContent = isBusy ? busyLabel : button.dataset.defaultLabel || button.textContent;
}

function setStatus(message, tone) {
    elements.status.textContent = message;
    elements.status.dataset.tone = tone || '';
}

function nullableValue(value) {
    const trimmedValue = value.trim();

    return trimmedValue === '' ? null : trimmedValue;
}

function sourcePlatformFor(pageUrl) {
    try {
        const hostname = new URL(pageUrl).hostname.toLowerCase();

        if (hostname.includes('linkedin.com')) {
            return 'linkedin';
        }
    } catch (_) {
        return 'web';
    }

    return 'web';
}

async function fetchCsrfToken() {
    const response = await fetch(CSRF_ENDPOINT, {
        method: 'GET',
        credentials: 'include',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    const responseData = await safeJson(response);

    if (response.status === 401) {
        throw new Error('Log into FindJobApp locally first, then try again.');
    }

    if (! response.ok || typeof responseData.token !== 'string' || responseData.token.trim() === '') {
        throw new Error('Could not fetch a CSRF token from FindJobApp.');
    }

    return responseData.token;
}

async function safeJson(response) {
    const contentType = response.headers.get('content-type') || '';

    if (! contentType.includes('application/json')) {
        return {};
    }

    try {
        return await response.json();
    } catch (_) {
        return {};
    }
}

function validationMessage(responseData) {
    const errors = responseData.errors || {};
    const firstKey = Object.keys(errors)[0];

    if (! firstKey || ! Array.isArray(errors[firstKey]) || errors[firstKey].length === 0) {
        return null;
    }

    return errors[firstKey][0];
}

function previewText(text) {
    return text.length > 220 ? `${text.slice(0, 217)}...` : text;
}

function extractedHttpUrl(text) {
    const match = text.match(/https?:\/\/[^\s)]+/i);

    return match ? match[0] : null;
}

function scoreCandidates(blocks) {
    const seenTexts = new Set();
    const candidates = [];

    for (const block of blocks) {
        const normalizedText = normalizeComparisonText(block.text);

        if (normalizedText.length < 80 || seenTexts.has(normalizedText)) {
            continue;
        }

        seenTexts.add(normalizedText);

        const hiringMatches = matchedTerms(block.text, HIRING_TERMS);
        const technicalMatches = matchedTerms(block.text, TECHNICAL_TERMS);
        const negativeMatches = matchedTerms(block.text, NEGATIVE_TERMS);

        if (hiringMatches.length === 0 || technicalMatches.length === 0) {
            continue;
        }

        const score = (hiringMatches.length * 3)
            + (technicalMatches.length * 2)
            - (negativeMatches.length * 3)
            + (block.text.length >= 180 ? 1 : 0);

        if (score < 4) {
            continue;
        }

        candidates.push({
            id: candidates.length + 1,
            text: block.text,
            score,
            highConfidence: negativeMatches.length === 0 && hiringMatches.length > 0 && technicalMatches.length > 0 && score >= 7,
        });
    }

    candidates.sort((left, right) => right.score - left.score || right.text.length - left.text.length);

    return candidates.slice(0, MAX_SCAN_CANDIDATES);
}

function matchedTerms(text, terms) {
    const haystack = normalizeComparisonText(text);

    return terms.filter((term) => haystack.includes(normalizeComparisonText(term)));
}

function normalizeComparisonText(text) {
    return text
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/\s+/g, ' ')
        .trim();
}

function capturePageContext() {
    const selectedText = window.getSelection()?.toString().trim() || '';
    const pageUrl = window.location.href;
    const pageTitle = document.title.trim();

    if (selectedText !== '') {
        return {
            capturedText: selectedText.slice(0, 10000),
            pageTitle,
            pageUrl,
        };
    }

    const visibleText = firstVisibleTextBlock();

    return {
        capturedText: visibleText.slice(0, 10000),
        pageTitle,
        pageUrl,
    };
}

function scanVisiblePostCandidates() {
    const pageUrl = window.location.href;
    const blocks = [];
    const seenTexts = new Set();
    const selectors = ['article', '[role="article"]', 'main div', 'section', 'div'];
    const nodes = document.querySelectorAll(selectors.join(','));

    for (const node of nodes) {
        if (! isVisible(node)) {
            continue;
        }

        const text = normalizeVisibleBlockText(node.innerText || '');

        if (text.length < 80 || seenTexts.has(text)) {
            continue;
        }

        seenTexts.add(text);
        blocks.push({ text });
    }

    return {
        blocks,
        pageUrl,
    };
}

function isVisible(node) {
    if (!(node instanceof HTMLElement)) {
        return false;
    }

    const style = window.getComputedStyle(node);
    const rect = node.getBoundingClientRect();

    if (style.display === 'none' || style.visibility === 'hidden') {
        return false;
    }

    if (rect.width === 0 || rect.height === 0) {
        return false;
    }

    return rect.bottom >= 0 && rect.top <= window.innerHeight;
}

function firstVisibleTextBlock() {
    const candidates = [
        document.querySelector('article'),
        document.querySelector('main'),
        document.body,
    ].filter(Boolean);

    for (const candidate of candidates) {
        const text = normalizeVisibleBlockText(candidate.innerText || '');

        if (text !== '') {
            return text;
        }
    }

    return '';
}

function normalizeVisibleBlockText(text) {
    const cleaned = text
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line !== '')
        .slice(0, 30)
        .join('\n');

    return cleaned.slice(0, 2400);
}

elements.sendButton.dataset.defaultLabel = 'Send to FindJobApp';
elements.scanButton.dataset.defaultLabel = 'Scan visible posts';
elements.sendSelectedButton.dataset.defaultLabel = 'Send selected to FindJobApp';
