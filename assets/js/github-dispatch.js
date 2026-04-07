(() => {
	const btn = document.getElementById('rrqr-github-dispatch');
	if (!btn || typeof rrqrGithubDispatch === 'undefined') {
		return;
	}

	const statusEl = document.getElementById('rrqr-github-dispatch-status');
	const { ajaxUrl, nonce, i18n } = rrqrGithubDispatch;

	btn.addEventListener('click', () => {
		if (statusEl) {
			statusEl.textContent = i18n.working;
		}
		btn.disabled = true;

		const fd = new FormData();
		fd.append('action', 'rrqr_github_dispatch');
		fd.append('nonce', nonce);
		fd.append('game_id', btn.getAttribute('data-game-id') || '');

		fetch(ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: fd,
		})
			.then((r) => r.json())
			.then((data) => {
				if (!statusEl) {
					return;
				}
				if (data.success && data.data && data.data.message) {
					statusEl.textContent = data.data.message;
				} else if (data.success) {
					statusEl.textContent = i18n.ok;
				} else {
					const err =
						typeof data.data === 'string'
							? data.data
							: data.data && data.data.message
								? data.data.message
								: i18n.err;
					statusEl.textContent = err;
				}
			})
			.catch(() => {
				if (statusEl) {
					statusEl.textContent = i18n.network;
				}
			})
			.finally(() => {
				btn.disabled = false;
			});
	});
})();
