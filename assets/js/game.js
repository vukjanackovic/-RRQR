(() => {
	const { escHtml } = rrqrUtils;

	const generateBtn = document.getElementById('rrqr-generate');
	if (!generateBtn) return;

	generateBtn.addEventListener('click', () => {
		let rows = '';

		document.querySelectorAll('.rrqr-player-row').forEach((el) => {
			const headshot = el.dataset.headshot;
			const name = el.dataset.name;
			const statline = el.dataset.statline;
			const reaction = (el.querySelector('textarea').value || '').trim();
			const grade = el.querySelector('select').value;

			if (!reaction) return;

			rows +=
				`<tr>
  <td style="vertical-align:top;text-align:center;width:80px;max-width:80px;">
    <img style="max-width:80px;height:auto;" src="${escHtml(headshot)}" alt="${escHtml(name)}" /><br />
    <div class="grade">${escHtml(grade)}</div>
  </td>
  <td style="vertical-align:top;">
    <span class="thn-reaction-player">${escHtml(name)}</span><br />
    <span class="thn-reaction-player-line">${escHtml(statline)}</span><br />
    ${escHtml(reaction).replace(/\n/g, '<br />')}
  </td>
</tr>\n`;
		});

		const gd = document.getElementById('rrqr-game-data');
		const awayLogo = rrqrGame.teamLogoUrl.replace('%s', gd.dataset.awayId);
		const homeLogo = rrqrGame.teamLogoUrl.replace('%s', gd.dataset.homeId);

		let things = '';
		document.querySelectorAll('.rrqr-things-we-saw textarea').forEach((ta) => {
			const val = (ta.value || '').trim();
			if (val) {
				things += `<li>${escHtml(val).replace(/\n/g, '<br />')}</li>\n`;
			}
		});

		const summaryHtml = things
			? `\n<div class="thn-reaction-summary">
<h4>${escHtml(rrqrGame.i18n.thingsWeSaw)}</h4>
<ol>
${things}</ol>
</div>`
			: '';

		const html =
			`<div class="thn-reaction">
<div class="thn-reaction-header">
<table border="0" cellpadding="0" cellspacing="0" width="100%"><tr>
  <td style="text-align:center;vertical-align:middle;width:33%;">
    <img src="${escHtml(awayLogo)}" alt="${escHtml(gd.dataset.awayName)}" style="max-width:60px;height:auto;" /><br />
    ${escHtml(gd.dataset.awayName)}
  </td>
  <td style="text-align:center;vertical-align:middle;width:34%;">
    <span class="thn-reaction-score">${escHtml(gd.dataset.awayScore)} - ${escHtml(gd.dataset.homeScore)}</span>
  </td>
  <td style="text-align:center;vertical-align:middle;width:33%;">
    <img src="${escHtml(homeLogo)}" alt="${escHtml(gd.dataset.homeName)}" style="max-width:60px;height:auto;" /><br />
    ${escHtml(gd.dataset.homeName)}
  </td>
</tr></table>
</div>
<div class="thn-reaction-grades">
<table border="0" cellpadding="0" cellspacing="0" width="100%">
${rows}</table>
</div>${summaryHtml}
</div>`;

		const output = document.getElementById('rrqr-output');
		const preview = document.getElementById('rrqr-preview');
		const actions = document.querySelector('.rrqr-action-buttons');

		output.value = html;
		output.style.display = 'block';
		preview.innerHTML = html;
		actions.style.display = 'flex';
	});

	const copyBtn = document.getElementById('rrqr-copy');
	if (copyBtn) {
		copyBtn.addEventListener('click', () => {
			const output = document.getElementById('rrqr-output');
			const text = output.value;
			if (!text) return;
			navigator.clipboard.writeText(text).then(() => {
				copyBtn.textContent = rrqrGame.i18n.copied;
				setTimeout(() => {
					copyBtn.textContent = rrqrGame.i18n.copyClipboard;
				}, 2000);
			}).catch(() => {
				output.select();
			});
		});
	}

})();
