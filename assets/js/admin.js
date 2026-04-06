(() => {
	const btn = document.getElementById('rrqr-start-generating');
	const results = document.getElementById('rrqr-results');

	if (!results) {
		return;
	}

	const formatDate = (str) => {
		if (!str) return '';
		const parts = str.split(/[\s\/]+/);
		const d = new Date(parts[2], parts[0] - 1, parts[1]);
		if (isNaN(d.getTime())) return str;
		return d.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
	};

	const showNotice = (message, type = 'error') => {
		results.textContent = '';
		const notice = document.createElement('div');
		notice.className = `notice notice-${type}`;
		const p = document.createElement('p');
		p.textContent = message;
		notice.appendChild(p);
		results.appendChild(notice);
	};

	const buildGamesTable = (games) => {
		const i18n = rrqrAdmin.i18n;
		const table = document.createElement('table');
		table.className = 'widefat striped rrqr-games-table';

		const thead = table.createTHead();
		const headerRow = thead.insertRow();
		[i18n.date, i18n.away, '', i18n.home, i18n.status, ''].forEach((text) => {
			const th = document.createElement('th');
			th.textContent = text;
			headerRow.appendChild(th);
		});

		const tbody = table.createTBody();
		for (const g of games) {
			const row = tbody.insertRow();

			row.insertCell().textContent = formatDate(g.date);
			row.insertCell().textContent = g.awayCity + ' ' + g.awayTeam;
			row.insertCell().textContent = '@';
			row.insertCell().textContent = g.homeCity + ' ' + g.homeTeam;
			row.insertCell().textContent = g.status;

			const actionCell = row.insertCell();
			const link = document.createElement('a');
			link.href = rrqrAdmin.gameUrl + '&game_id=' + encodeURIComponent(g.gameId) + '&_wpnonce=' + encodeURIComponent(rrqrAdmin.gameUrlNonce);
			link.className = 'button button-secondary';
			link.textContent = i18n.generateReaction;
			actionCell.appendChild(link);
		}

		results.textContent = '';
		results.appendChild(table);
	};

	const loadGames = (refresh) => {
		if (btn) {
			btn.disabled = true;
			btn.textContent = rrqrAdmin.i18n.loading;
		}
		results.textContent = '';
		const p = document.createElement('p');
		p.textContent = rrqrAdmin.i18n.loading;
		results.appendChild(p);

		const body = new URLSearchParams();
		body.append('action', 'rrqr_fetch_games');
		body.append('nonce', rrqrAdmin.nonce);
		if (refresh) {
			body.append('refresh', '1');
		}

		fetch(rrqrAdmin.ajaxUrl, {
			method: 'POST',
			body,
		})
			.then((res) => {
				if (!res.ok) throw new Error(res.statusText);
				return res.json();
			})
			.then((resp) => {
				if (btn) {
					btn.disabled = false;
					btn.textContent = rrqrAdmin.i18n.refresh;
				}

				if (!resp.success) {
					showNotice(resp.data || rrqrAdmin.i18n.unknownError);
					return;
				}

				const games = resp.data.sort((a, b) => new Date(b.date) - new Date(a.date));
				buildGamesTable(games);
			})
			.catch(() => {
				if (btn) {
					btn.disabled = false;
					btn.textContent = rrqrAdmin.i18n.refresh;
				}
				showNotice(rrqrAdmin.i18n.networkError);
			});
	};

	if (btn) {
		btn.addEventListener('click', () => loadGames(true));
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', loadGames);
	} else {
		loadGames();
	}
})();
