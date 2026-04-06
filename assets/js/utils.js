const rrqrUtils = (() => {
	const escHtml = (str) =>
		String(str || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');

	return { escHtml };
})();
