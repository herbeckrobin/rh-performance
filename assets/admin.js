/**
 * Performance-Diagnose: Seiten-Scoring (Sitemap laden, Scan-Tabelle, PSI-Detail
 * pro Zeile) und Memory-Verlauf-Reset. Buildless, kein Framework.
 */
(function () {
	'use strict';

	var cfg = window.rhPerfDiag;
	if (!cfg) {
		return;
	}

	function el(tag, cls, text) {
		var e = document.createElement(tag);
		if (cls) {
			e.className = cls;
		}
		if (text != null) {
			e.textContent = text;
		}
		return e;
	}

	function shortUrl(u) {
		try {
			var x = new URL(u);
			return x.pathname === '/' ? x.host : x.pathname + x.search;
		} catch (e) {
			return u;
		}
	}

	function scoreClass(s) {
		return s >= 90 ? 'ok' : s >= 50 ? 'info' : 'warn';
	}

	function post(action, nonce, extra) {
		var body = new URLSearchParams();
		body.set('action', action);
		body.set('nonce', nonce);
		Object.keys(extra || {}).forEach(function (k) {
			body.set(k, extra[k]);
		});
		return fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		}).then(function (r) {
			return r.json();
		});
	}

	// --- Memory-Verlauf zuruecksetzen ---
	var memReset = document.getElementById('rhbp-perf-memreset');
	if (memReset && cfg.memReset) {
		memReset.addEventListener('click', function () {
			memReset.disabled = true;
			post(cfg.memReset.action, cfg.memReset.nonce)
				.then(function () {
					window.location.reload();
				})
				.catch(function () {
					memReset.disabled = false;
				});
		});
	}

	// --- Seiten-Scoring ---
	var scanSelect = document.getElementById('rhbp-perf-scan-target');
	var scanBtn = document.getElementById('rhbp-perf-scan');
	var scanRows = document.getElementById('rhbp-perf-scan-rows');
	var scanNote = document.getElementById('rhbp-perf-scan-note');
	var scanError = document.getElementById('rhbp-perf-scan-error');

	function cell(text, cls) {
		return el('td', cls || null, text);
	}

	function pill(text, variant) {
		return el('span', 'rhbp-pill ' + variant, text);
	}

	function renderScan(data) {
		scanRows.innerHTML = '';
		if (data.total > data.scanned) {
			scanNote.textContent = cfg.i18n.scannedOf
				.replace('%1$d', data.scanned)
				.replace('%2$d', data.total);
			scanNote.hidden = false;
		}

		var table = el('table', 'rhbp-table rhbp-perf-table');
		var thead = el('thead');
		var htr = el('tr');
		[cfg.i18n.colPage, cfg.i18n.colScore, cfg.i18n.colTime, cfg.i18n.colHtml, cfg.i18n.colCache, cfg.i18n.colCss, cfg.i18n.colJs, cfg.i18n.colImg, cfg.i18n.colBlock, ''].forEach(function (h) {
			htr.appendChild(el('th', null, h));
		});
		thead.appendChild(htr);
		table.appendChild(thead);

		var tbody = el('tbody');
		data.rows.forEach(function (row) {
			var tr = el('tr');
			var urlCell = el('td', 'rhbp-perf-cell-url');
			urlCell.title = row.url;
			urlCell.textContent = shortUrl(row.url);
			tr.appendChild(urlCell);

			if (!row.ok) {
				var errTd = el('td');
				errTd.colSpan = 9;
				errTd.appendChild(pill(row.error || cfg.i18n.unreachable, 'rhbp-pill--err'));
				tr.appendChild(errTd);
				tbody.appendChild(tr);
				return;
			}

			var scoreTd = el('td');
			scoreTd.appendChild(el('span', 'rhbp-perf-badge rhbp-perf-badge--' + scoreClass(row.score), String(row.score)));
			tr.appendChild(scoreTd);

			tr.appendChild(cell(row.time_ms + ' ms'));
			tr.appendChild(cell(row.html_kb + ' KB'));

			var cacheTd = el('td');
			cacheTd.appendChild(pill(row.cacheable ? cfg.i18n.yes : cfg.i18n.no, row.cacheable ? 'rhbp-pill--ok' : 'rhbp-pill--err'));
			tr.appendChild(cacheTd);

			tr.appendChild(cell(String(row.css)));
			tr.appendChild(cell(String(row.js)));
			tr.appendChild(cell(String(row.img)));
			tr.appendChild(cell(String(row.render_blocking_css), row.render_blocking_css > 3 ? 'rhbp-perf-warn' : null));

			var psiTd = el('td');
			var psiBtn = el('button', 'rhbp-btn rhbp-btn--ghost rhbp-perf-row__psi', cfg.i18n.psi);
			psiBtn.type = 'button';
			psiBtn.dataset.url = row.url;
			psiTd.appendChild(psiBtn);
			tr.appendChild(psiTd);
			tbody.appendChild(tr);

			var detailRow = el('tr', 'rhbp-perf-detail-row');
			detailRow.hidden = true;
			var detailTd = el('td', 'rhbp-perf-row__detail');
			detailTd.colSpan = 10;
			detailRow.appendChild(detailTd);
			tbody.appendChild(detailRow);
		});

		table.appendChild(tbody);
		var wrap = el('div', 'rhbp-perf-tablewrap');
		wrap.appendChild(table);
		scanRows.appendChild(wrap);
	}

	function renderPsi(detail, data) {
		detail.innerHTML = '';
		var top = el('div', 'rhbp-perf-psi__top');
		if (data.score != null) {
			top.appendChild(el('span', 'rhbp-perf-badge rhbp-perf-badge--' + scoreClass(data.score), String(data.score)));
		}
		top.appendChild(el('span', 'rhbp-perf-psi__label', cfg.i18n.psiScore));
		if (data.field) {
			top.appendChild(pill(cfg.i18n.psiField + ': ' + data.field, ''));
		}
		detail.appendChild(top);

		if (data.metrics && data.metrics.length) {
			var metrics = el('div', 'rhbp-perf-psi__metrics');
			data.metrics.forEach(function (m) {
				var chip = el('span', 'rhbp-perf-psi__metric');
				chip.appendChild(el('span', 'rhbp-perf-chip__k', m.label));
				chip.appendChild(document.createTextNode(' ' + m.value));
				metrics.appendChild(chip);
			});
			detail.appendChild(metrics);
		}

		detail.appendChild(el('div', 'rhbp-perf-psi__optitle', cfg.i18n.opportunities));
		if (data.opportunities && data.opportunities.length) {
			var list = el('ul', 'rhbp-perf-psi__opps');
			data.opportunities.forEach(function (o) {
				var li = el('li');
				li.appendChild(el('span', 'rhbp-perf-psi__opplabel', o.label));
				li.appendChild(el('span', 'rhbp-perf-psi__oppms', cfg.i18n.savingsMs.replace('%d', o.savings_ms)));
				list.appendChild(li);
			});
			detail.appendChild(list);
		} else {
			detail.appendChild(el('div', 'rhbp-perf-psi__none', cfg.i18n.noOpportunities));
		}
	}

	if (scanSelect && scanBtn && cfg.scan) {
		post(cfg.scan.sitemapAction, cfg.scan.nonce).then(function (json) {
			scanSelect.innerHTML = '';
			var all = el('option', null, cfg.i18n.wholeSite);
			all.value = 'all';
			scanSelect.appendChild(all);
			if (json && json.success && json.data.urls) {
				json.data.urls.forEach(function (u) {
					var o = el('option', null, shortUrl(u));
					o.value = u;
					scanSelect.appendChild(o);
				});
			}
			scanSelect.disabled = false;
			scanBtn.disabled = false;
		});

		scanBtn.addEventListener('click', function () {
			scanBtn.disabled = true;
			scanBtn.textContent = cfg.i18n.scanRunning;
			scanError.hidden = true;
			scanNote.hidden = true;
			scanRows.innerHTML = '';
			post(cfg.scan.scanAction, cfg.scan.nonce, { target: scanSelect.value })
				.then(function (json) {
					if (!json || !json.success) {
						throw new Error((json && json.data && json.data.message) || cfg.i18n.failed);
					}
					renderScan(json.data);
				})
				.catch(function (err) {
					scanError.textContent = err.message;
					scanError.hidden = false;
				})
				.finally(function () {
					scanBtn.disabled = false;
					scanBtn.textContent = cfg.i18n.scanRun;
				});
		});
	}

	if (scanRows) {
		scanRows.addEventListener('click', function (e) {
			var btn = e.target.closest('.rhbp-perf-row__psi');
			if (!btn) {
				return;
			}
			var detailRow = btn.closest('tr').nextElementSibling;
			var detail = detailRow.querySelector('.rhbp-perf-row__detail');

			// Schon geladen: nur auf-/zuklappen.
			if (detail.dataset.loaded) {
				detailRow.hidden = !detailRow.hidden;
				return;
			}

			btn.disabled = true;
			btn.textContent = cfg.i18n.psiRunning;
			post(cfg.scan.psiAction, cfg.scan.nonce, { url: btn.dataset.url, strategy: 'mobile' })
				.then(function (json) {
					if (!json || !json.success) {
						throw new Error((json && json.data && json.data.message) || cfg.i18n.failed);
					}
					renderPsi(detail, json.data);
					detail.dataset.loaded = '1';
					detailRow.hidden = false;
				})
				.catch(function (err) {
					detail.innerHTML = '';
					detail.appendChild(el('div', 'rhbp-callout rhbp-callout--warn', err.message));
					detailRow.hidden = false;
				})
				.finally(function () {
					btn.disabled = false;
					btn.textContent = cfg.i18n.psi;
				});
		});
	}

	// --- Einstellungen: Switches + PSI-Key (AJAX, kein Reload) ---
	if (cfg.setting) {
		document.querySelectorAll('[data-perf-toggle]').forEach(function (input) {
			input.addEventListener('change', function () {
				post(cfg.setting.action, cfg.setting.nonce, {
					field: input.dataset.perfToggle,
					value: input.checked ? '1' : '0'
				}).catch(function () {
					input.checked = !input.checked;
				});
			});
		});

		var psiSave = document.getElementById('rhbp-perf-psikey-save');
		var psiInput = document.getElementById('rhbp-perf-psikey-input');
		if (psiSave && psiInput) {
			psiSave.addEventListener('click', function () {
				psiSave.disabled = true;
				post(cfg.setting.action, cfg.setting.nonce, {
					field: cfg.setting.psiField,
					value: psiInput.value
				})
					.then(function () {
						var modal = psiSave.closest('.rhbp-modal');
						var closer = modal && modal.querySelector('[data-rhbp-modal-close]');
						if (closer) {
							closer.click();
						}
					})
					.finally(function () {
						psiSave.disabled = false;
					});
			});
		}
	}
})();
