/* global WooriseConditions, wp */
(function () {
	'use strict';

	const { createElement: h, useState, useEffect, Fragment } = wp.element;
	const { __ } = wp.i18n;
	const {
		Button,
		SelectControl,
		TextControl,
		FormTokenField,
		Card,
		CardBody,
	} = wp.components;

	const FIELDS = (window.WooriseConditions && WooriseConditions.fields) || [];
	const OPS    = (window.WooriseConditions && WooriseConditions.operators) || {};
	const CPTS   = (window.WooriseConditions && WooriseConditions.cpts) || [];

	const cptSlugToLabel = Object.fromEntries((CPTS || []).map(o => [String(o.slug), String(o.label)]));
	const cptLabelToSlug = Object.fromEntries((CPTS || []).map(o => [String(o.label), String(o.slug)]));

	function opOptionsFor(fieldKey) {
		const meta = FIELDS.find(f => f.key === fieldKey);
		if (!meta) return [];
		if (meta.type === 'scope_site') return [];

		const bucket = OPS[meta.type] || {};
		return Object.keys(bucket).map(k => ({ label: String(bucket[k]), value: k }));
	}

	function RuleRow({ value, onChange, onRemove }) {
		const fieldMeta = FIELDS.find(f => f.key === value.field);
		const opOpts    = fieldMeta ? opOptionsFor(fieldMeta.key) : [];

		const WCPAGES = (window.WooriseConditions && Array.isArray(WooriseConditions.wcPages))
			? WooriseConditions.wcPages : [];
		const wcSlugToLabel = Object.fromEntries(WCPAGES.map(o => [String(o.slug), String(o.label)]));
		const wcLabelToSlug = Object.fromEntries(WCPAGES.map(o => [String(o.label), String(o.slug)]));

		function update(next) {
			onChange({ ...value, ...next });
		}

		function renderValueControl() {
			if (!fieldMeta) return null;

			switch (fieldMeta.type) {
				case 'scope_site':
					return null;

				case 'scope_singular':
				case 'scope_archive': {
					const currentSlugs  = Array.isArray(value.value) ? value.value : [];
					const currentTokens = currentSlugs.map(slug => cptSlugToLabel[slug] || slug);
					const suggestions   = (CPTS || []).map(c => c.label);

					return h(FormTokenField, {
						label: __('Post types', 'woorise'),
						className: 'wr-token-field',
						value: currentTokens,
						suggestions,
						onChange: (tokens) => {
							const nextSlugs = tokens
								.map(t => cptLabelToSlug[t] || t)
								.filter(Boolean);
							update({ value: nextSlugs });
						},
						placeholder: __('Type to add post types…', 'woorise'),
						__experimentalExpandOnFocus: true,
						__experimentalShowHowTo: false
					});
				}

				case 'wc': {
					const currentSlugs  = Array.isArray(value.value) ? value.value : [];
					const currentTokens = currentSlugs.map(slug => wcSlugToLabel[slug] || slug);
					const suggestions   = WCPAGES.map(p => p.label);

					return h(FormTokenField, {
						label: __('WooCommerce pages', 'woorise'),
						className: 'wr-token-field',
						value: currentTokens,
						suggestions,
						onChange: (tokens) => {
							const nextSlugs = tokens
								.map(t => wcLabelToSlug[t] || t)
								.filter(Boolean);
							update({ value: nextSlugs });
						},
						placeholder: __('Type to add pages…', 'woorise'),
						__experimentalExpandOnFocus: true,
						__experimentalShowHowTo: false
					});
				}

				case 'enum': {
					if (value.field === 'user') {
						if (value.value !== 'logged_in') {
							update({ value: 'logged_in' });
						}
						return h(SelectControl, {
							label: __('State', 'woorise'),
							value: 'logged_in',
							options: [ { label: __('Logged-in', 'woorise'), value: 'logged_in' } ],
							disabled: true,
						});
					}
					return null;
				}

				case 'ids':
					return h(TextControl, {
						label: __('Spesific pages (IDs)', 'woorise'),
						placeholder: __('e.g. 12, 45, 90', 'woorise'),
						value: Array.isArray(value.value) ? value.value.join(',') : (value.value || ''),
						onChange: (v) => {
							const ids = String(v)
								.split(',')
								.map(s => parseInt(s.trim(), 10))
								.filter(n => Number.isFinite(n) && n > 0);
							update({ value: ids });
						},
					});

				case 'text':
					return h(TextControl, {
						label: __('URL match', 'woorise'),
						placeholder: __('e.g. utm_source, /products, lang=en', 'woorise'),
						value: typeof value.value === 'string' ? value.value : '',
						onChange: (v) => update({ value: v }),
					});
			}
			return null;
		}

		function onFieldChange(nextField) {
			const meta = FIELDS.find(f => f.key === nextField);
			let next = { field: nextField, operator: '', value: '' };
			if (nextField === 'user') next.value = 'logged_in';
			if (meta && (meta.type === 'scope_singular' || meta.type === 'scope_archive' || meta.type === 'wc')) {
				next.value = [];
			}
			update(next);
		}

		return h(
			'div',
			{ className: 'wr-cond-row' },
			h('div', { className: 'wr-cell' },
				h(SelectControl, {
					label: __('Condition', 'woorise'),
					value: value.field || '',
					options: [{ label: __('Select field…', 'woorise'), value: '' }].concat(
						FIELDS.map(f => ({ label: f.label, value: f.key }))
					),
					onChange: onFieldChange,
				})
			),
			h('div', { className: 'wr-cell' },
				fieldMeta && fieldMeta.type !== 'scope_site'
					? h(SelectControl, {
						label: __('Operator', 'woorise'),
						value: value.operator || '',
						options: [{ label: __('Select operator…', 'woorise'), value: '' }].concat(opOpts),
						onChange: (v) => update({ operator: v }),
					})
					: h(Fragment)
			),
			h('div', { className: 'wr-cell' }, renderValueControl()),
			h('div', { className: 'wr-actions' },
				h(Button, {
					variant: 'tertiary',
					isSmall: true,
					onClick: onRemove,
					'aria-label': __('Remove', 'woorise'),
					className: 'wr-icon-btn',
				}, h('span', { className: 'dashicons dashicons-no-alt', 'aria-hidden': 'true' }))
			)
		);
	}

	function AndGroup({ rules, onChange, onAddRule, onRemoveRule }) {
		return h(
			Card,
			{ className: 'wr-cond-group', isRounded: false, isBorderless: true },
			h(
				CardBody,
				null,
				(rules || []).map((rule, idx) =>
					h(RuleRow, {
						key: 'r' + idx,
						value: rule,
						onChange: (next) => {
							const copy = rules.slice();
							copy[idx] = next;
							onChange(copy);
						},
						onRemove: () => onRemoveRule(idx),
					})
				),
				h(Button, { variant: 'secondary', onClick: onAddRule }, __('Add AND', 'woorise'))
			)
		);
	}

	function OrSeparator() {
		return h('div', { className: 'wr-or-sep' }, __('or', 'woorise'));
	}

	function sanitizeRulesForPost(groups) {
		if (!Array.isArray(groups)) return [];
		return groups
			.map((g) => (Array.isArray(g) ? g : []))
			.map((rules) =>
				rules.map((r) => {
					const field    = typeof r.field === 'string' ? r.field : '';
					const operator = typeof r.operator === 'string' ? r.operator : '';
					let value      = r.value;

					const meta = FIELDS.find(f => f.key === field);
					if (!meta) return { field: '', operator: '', value: '' };

					if (meta.type === 'scope_site') {
						return { field, operator: '', value: '' };
					}
					if (field === 'user') {
						value = 'logged_in';
					} else if (meta.type === 'scope_singular' || meta.type === 'scope_archive' || meta.type === 'wc') {
						value = Array.isArray(value) ? value.slice() : [];
					} else if (meta.type === 'ids') {
						value = Array.isArray(value)
							? value.map(v => parseInt(v, 10)).filter(n => Number.isFinite(n) && n > 0)
							: [];
					} else if (meta.type === 'enum') {
						value = typeof value === 'string' ? value : '';
					} else if (meta.type === 'text') {
						value = typeof value === 'string' ? value : '';
					}
					return { field, operator, value };
				})
			)
			.filter(g => g.length > 0);
	}

	function ConditionBuilder() {
		const root = document.getElementById('wr-cond-react');
		const initial = root ? root.getAttribute('data-initial') : '[]';
		let parsed = [];
		try { parsed = JSON.parse(initial || '[]'); } catch { parsed = []; }
		if (!Array.isArray(parsed) || parsed.length === 0) {
			parsed = [[{ field: '', operator: '', value: '' }]];
		}

		const [groups, setGroups] = useState(parsed);

		function syncHidden(nextGroups) {
			const sanitized = sanitizeRulesForPost(nextGroups ?? groups);
			const hidden = document.getElementById('woorise_rules');
			if (hidden) {
				hidden.value = JSON.stringify(sanitized);
			}
		}

		useEffect(() => { syncHidden(); }, [groups]);

		useEffect(() => {
			const form = document.getElementById('post') || document.querySelector('form#post');
			if (!form) return;
			const handler = () => { syncHidden(); };
			form.addEventListener('submit', handler, true);
			return () => form.removeEventListener('submit', handler, true);
		}, [groups]);

		function addOrGroup() {
			const next = groups.concat([[{ field: '', operator: '', value: '' }]]);
			setGroups(next);
		}

		function removeRuleAt(groupIdx, ruleIdx) {
			const next = groups.map(g => g.slice());

			if (next[groupIdx].length === 1) {

				if (next.length > 1) {
					next.splice(groupIdx, 1);
				} else {
					next[groupIdx] = [{ field: '', operator: '', value: '' }];
				}
			} else {
				next[groupIdx].splice(ruleIdx, 1);
			}

			setGroups(next);
			syncHidden(next);
		}

		function addAndTo(index) {
			const next = groups.map(g => g.slice());
			next[index].push({ field: '', operator: '', value: '' });
			setGroups(next);
		}

		function updateGroup(index, rules) {
			const next = groups.slice();
			next[index] = rules;
			setGroups(next);
		}

		return h(
			'div',
			{ className: 'wr-cond-root' },
			groups.map((rules, gi) =>
				h(
					Fragment,
					{ key: 'g' + gi },
					gi > 0 ? h(OrSeparator) : null,
					h('div', { className: 'wr-cond-group-wrap' },
						h(AndGroup, {
							rules,
							onChange: (r) => updateGroup(gi, r),
							onAddRule: () => addAndTo(gi),
							onRemoveRule: (ri) => removeRuleAt(gi, ri),
						})
					)
				)
			),
			h(Button, { 
				variant: 'secondary', onClick: addOrGroup }, __('Add OR', 'woorise')
			)
		);
	}

	function mount() {
		const rootEl = document.getElementById('wr-cond-react');
		if (!rootEl) return;
		if (wp.element && typeof wp.element.createRoot === 'function') {
			wp.element.createRoot(rootEl).render(h(ConditionBuilder));
		} else if (typeof wp.element.render === 'function') {
			wp.element.render(h(ConditionBuilder), rootEl);
		}
	}

	if (document.readyState === 'complete' || document.readyState === 'interactive') {
		mount();
	} else {
		document.addEventListener('DOMContentLoaded', mount);
	}
})();
