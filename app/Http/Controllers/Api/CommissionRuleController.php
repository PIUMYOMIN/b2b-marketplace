import React, { useState, useEffect, useCallback } from 'react';
import {
CurrencyDollarIcon,
PencilSquareIcon,
CheckCircleIcon,
XCircleIcon,
PlusIcon,
ExclamationCircleIcon,
} from '@heroicons/react/24/outline';
import api from '../../utils/api';

// ── Config ──────────────────────────────────────────────────────────────────
const TIER_LABELS = { 1: '🥉 Bronze', 2: '🥈 Silver', 3: '🥇 Gold' };
const TYPE_LABELS = {
default: { label: 'Platform Default', color: 'bg-gray-100 text-gray-700' },
account_level: { label: 'Seller Tier', color: 'bg-blue-100 text-blue-700' },
category: { label: 'Category', color: 'bg-purple-100 text-purple-700' },
business_type: { label: 'Business Type', color: 'bg-orange-100 text-orange-700' },
};

// ── Inline editable rate cell ────────────────────────────────────────────────
const RateCell = ({ rule, onSave }) => {
const [editing, setEditing] = useState(false);
const [val, setVal] = useState((Number(rule.rate) * 100).toFixed(2));
const [saving, setSaving] = useState(false);

const save = async () => {
const rate = parseFloat(val) / 100;
if (isNaN(rate) || rate < 0 || rate> 1) return;
  setSaving(true);
  try {
  await onSave(rule.id, { rate: rate.toFixed(4) });
  setEditing(false);
  } finally { setSaving(false); }
  };

  if (editing) return (
  <div className="flex items-center gap-1">
    <input autoFocus type="number" min="0" max="100" step="0.5" value={val} onChange={e=> setVal(e.target.value)}
    onKeyDown={e => { if (e.key === 'Enter') save(); if (e.key === 'Escape') setEditing(false); }}
    className="w-20 border border-blue-300 rounded px-2 py-1 text-sm focus:ring-1 focus:ring-blue-500
    focus:outline-none"
    />
    <span className="text-sm text-gray-500">%</span>
    <button onClick={save} disabled={saving} className="text-green-600 hover:text-green-700 disabled:opacity-40">
      <CheckCircleIcon className="h-5 w-5" />
    </button>
    <button onClick={()=> setEditing(false)} className="text-gray-400 hover:text-gray-600">
      <XCircleIcon className="h-5 w-5" />
    </button>
  </div>
  );

  return (
  <button onClick={()=> setEditing(true)}
    className="flex items-center gap-1.5 group"
    >
    <span className="text-sm font-bold text-gray-900">
      {(Number(rule.rate) * 100).toFixed(1)}%
    </span>
    <PencilSquareIcon className="h-3.5 w-3.5 text-gray-300 group-hover:text-blue-500 transition-colors" />
  </button>
  );
  };

  // ── New rule form ───────────────────────────────────────────────────────────
  const NewRuleForm = ({ categories, businessTypes, onCreated, onCancel }) => {
  const [form, setForm] = useState({ type: 'category', reference_id: '', rate: '5.0', notes: '' });
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  const set = (k, v) => setForm(p => ({ ...p, [k]: v }));

  const refOptions = () => {
  if (form.type === 'account_level') return Object.entries(TIER_LABELS).map(([id, label]) => ({ id, label }));
  if (form.type === 'category') return categories.map(c => ({ id: c.id, label: c.name }));
  if (form.type === 'business_type') return businessTypes.map(b => ({ id: b.id, label: b.name }));
  return [];
  };

  const submit = async () => {
  if (!form.rate || isNaN(parseFloat(form.rate))) { setError('Enter a valid rate.'); return; }
  if (form.type !== 'default' && !form.reference_id) { setError('Select a reference.'); return; }
  setSaving(true); setError('');
  try {
  await api.post('/admin/commission-rules', {
  type: form.type,
  reference_id: form.type === 'default' ? null : form.reference_id,
  rate: (parseFloat(form.rate) / 100).toFixed(4),
  notes: form.notes || null,
  is_active: true,
  });
  onCreated();
  } catch (e) {
  setError(e.response?.data?.message || 'Failed to create rule.');
  } finally { setSaving(false); }
  };

  return (
  <div className="bg-blue-50 border border-blue-200 rounded-xl p-4 space-y-3">
    <p className="text-sm font-semibold text-blue-900">New Commission Rule</p>
    {error && <p className="text-xs text-red-600">{error}</p>}
    <div className="grid grid-cols-1 sm:grid-cols-4 gap-3">
      <div>
        <label className="text-xs font-medium text-gray-600 block mb-1">Type</label>
        <select value={form.type} onChange={e=> { set('type', e.target.value); set('reference_id', ''); }}
          className="w-full text-sm border border-gray-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-blue-500
          focus:outline-none bg-white">
          <option value="category">Category</option>
          <option value="business_type">Business Type</option>
          <option value="account_level">Seller Tier</option>
        </select>
      </div>
      {form.type !== 'default' && (
      <div>
        <label className="text-xs font-medium text-gray-600 block mb-1">Reference</label>
        <select value={form.reference_id} onChange={e=> set('reference_id', e.target.value)}
          className="w-full text-sm border border-gray-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-blue-500
          focus:outline-none bg-white">
          <option value="">Select…</option>
          {refOptions().map(o => <option key={o.id} value={o.id}>{o.label}</option>)}
        </select>
      </div>
      )}
      <div>
        <label className="text-xs font-medium text-gray-600 block mb-1">Rate (%)</label>
        <input type="number" min="0" max="50" step="0.5" value={form.rate} onChange={e=> set('rate', e.target.value)}
        className="w-full text-sm border border-gray-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-blue-500
        focus:outline-none" />
      </div>
      <div>
        <label className="text-xs font-medium text-gray-600 block mb-1">Notes</label>
        <input type="text" value={form.notes} onChange={e=> set('notes', e.target.value)} placeholder="Optional"
        className="w-full text-sm border border-gray-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-blue-500
        focus:outline-none" />
      </div>
    </div>
    <div className="flex gap-2 pt-1">
      <button onClick={submit} disabled={saving}
        className="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg disabled:opacity-50 transition-colors">
        {saving ? 'Saving…' : 'Create Rule'}
      </button>
      <button onClick={onCancel}
        className="px-4 py-1.5 border border-gray-200 text-sm text-gray-600 rounded-lg hover:bg-gray-50 transition-colors">
        Cancel
      </button>
    </div>
  </div>
  );
  };

  // ── Main Component ──────────────────────────────────────────────────────────
  const CommissionRulesManagement = () => {
  const [rules, setRules] = useState([]);
  const [categories, setCategories] = useState([]);
  const [businessTypes, setBusinessTypes]= useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [showForm, setShowForm] = useState(false);
  const [toast, setToast] = useState('');

  const showToast = (msg) => { setToast(msg); setTimeout(() => setToast(''), 3000); };

  const load = useCallback(async () => {
  setLoading(true);
  setError(null);
  try {
  const [rulesRes, catsRes, bizRes] = await Promise.all([
  api.get('/admin/commission-rules'),
  api.get('/categories'),
  api.get('/business-types'),
  ]);
  setRules(rulesRes.data?.data || []);
  setCategories(catsRes.data?.data || catsRes.data || []);
  setBusinessTypes(bizRes.data?.data || bizRes.data || []);
  } catch (e) {
  setError('Failed to load commission rules.');
  } finally {
  setLoading(false);
  }
  }, []);

  useEffect(() => { load(); }, [load]);

  const handleSave = async (id, updates) => {
  await api.put(`/admin/commission-rules/${id}`, updates);
  setRules(prev => prev.map(r => r.id === id ? { ...r, ...updates } : r));
  showToast('Rate updated.');
  };

  const handleToggle = async (rule) => {
  const next = !rule.is_active;
  await api.put(`/admin/commission-rules/${rule.id}`, { is_active: next });
  setRules(prev => prev.map(r => r.id === rule.id ? { ...r, is_active: next } : r));
  showToast(next ? 'Rule activated.' : 'Rule deactivated.');
  };

  const refLabel = (rule) => {
  if (rule.type === 'default') return 'Platform-wide';
  if (rule.type === 'account_level') return TIER_LABELS[rule.reference_id] || `Tier ${rule.reference_id}`;
  if (rule.reference_label) return rule.reference_label;
  if (rule.type === 'category') return categories.find(c => c.id == rule.reference_id)?.name || `#${rule.reference_id}`;
  if (rule.type === 'business_type') return businessTypes.find(b => b.id == rule.reference_id)?.name ||
  `#${rule.reference_id}`;
  return `#${rule.reference_id}`;
  };

  // Group by type for display
  const grouped = {
  default: rules.filter(r => r.type === 'default'),
  account_level: rules.filter(r => r.type === 'account_level'),
  category: rules.filter(r => r.type === 'category'),
  business_type: rules.filter(r => r.type === 'business_type'),
  };

  if (loading) return (
  <div className="flex justify-center items-center h-48">
    <div className="animate-spin rounded-full h-10 w-10 border-t-2 border-b-2 border-green-500" />
  </div>
  );

  return (
  <div className="space-y-6">

    {/* Toast */}
    {toast && (
    <div
      className="fixed top-4 right-4 z-50 bg-green-600 text-white px-4 py-2.5 rounded-xl shadow-lg text-sm flex items-center gap-2">
      <CheckCircleIcon className="h-4 w-4" /> {toast}
    </div>
    )}

    {/* Header */}
    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <h2 className="text-xl font-bold text-gray-900 flex items-center gap-2">
          <CurrencyDollarIcon className="h-5 w-5 text-green-600" />
          Commission Rules
        </h2>
        <p className="text-sm text-gray-500 mt-0.5">
          Priority: Seller Tier → Business Type → Category → Platform Default
        </p>
      </div>
      <button onClick={()=> setShowForm(v => !v)}
        className="flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium
        rounded-xl transition-colors"
        >
        <PlusIcon className="h-4 w-4" />
        New Rule
      </button>
    </div>

    {error && (
    <div className="flex items-center gap-2 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
      <ExclamationCircleIcon className="h-4 w-4 flex-shrink-0" /> {error}
    </div>
    )}

    {/* New rule form */}
    {showForm && (
    <NewRuleForm categories={categories} businessTypes={businessTypes} onCreated={()=> { setShowForm(false); load();
      showToast('Rule created.'); }}
      onCancel={() => setShowForm(false)}
      />
      )}

      {/* Priority callout */}
      <div className="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-xs text-amber-800">
        <strong>How priority works:</strong> For each order, the platform checks rules in order.
        The first match wins: <strong>Tier</strong> → <strong>Business Type</strong> → <strong>Category</strong> →
        <strong>Default</strong>.
        A Gold seller always pays the Gold rate regardless of what category they sell in.
        Click any rate to edit it inline. Toggle the switch to activate/deactivate a rule.
      </div>

      {/* Rules by group */}
      {[
      ['default', 'Platform Default'],
      ['account_level', 'Seller Tiers'],
      ['category', 'Categories'],
      ['business_type', 'Business Types'],
      ].map(([type, heading]) => {
      const group = grouped[type];
      const meta = TYPE_LABELS[type];
      return (
      <div key={type}>
        <div className="flex items-center gap-2 mb-2">
          <span className={`text-xs font-semibold px-2 py-0.5 rounded-full ${meta.color}`}>{meta.label}</span>
          <span className="text-xs text-gray-400">{group.length} rule{group.length !== 1 ? 's' : ''}</span>
        </div>
        <div className="bg-white rounded-xl border border-gray-100 overflow-hidden">
          {group.length === 0 ? (
          <p className="text-xs text-gray-400 px-5 py-4">
            No {heading.toLowerCase()} rules yet.{type !== 'default' && ' Category and business type rules let you
            override the default rate for specific segments.'}
          </p>
          ) : (
          <table className="min-w-full text-sm">
            <thead className="bg-gray-50 text-xs font-medium text-gray-500 uppercase tracking-wide">
              <tr>
                <th className="px-5 py-2.5 text-left">Reference</th>
                <th className="px-5 py-2.5 text-left">Rate</th>
                <th className="px-5 py-2.5 text-left hidden sm:table-cell">Notes</th>
                <th className="px-5 py-2.5 text-right">Active</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-50">
              {group.map(rule => (
              <tr key={rule.id} className={`${!rule.is_active ? 'opacity-50' : '' } hover:bg-gray-50`}>
                <td className="px-5 py-3 font-medium text-gray-900">{refLabel(rule)}</td>
                <td className="px-5 py-3">
                  <RateCell rule={rule} onSave={handleSave} />
                </td>
                <td className="px-5 py-3 text-gray-400 text-xs hidden sm:table-cell">
                  {rule.notes || '—'}
                </td>
                <td className="px-5 py-3 text-right">
                  <button onClick={()=> handleToggle(rule)}
                    className={`relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2
                    border-transparent transition-colors duration-200 focus:outline-none ${
                    rule.is_active ? 'bg-green-500' : 'bg-gray-200'
                    }`}
                    >
                    <span className={`inline-block h-4 w-4 transform rounded-full bg-white shadow transition
                      duration-200 ${ rule.is_active ? 'translate-x-4' : 'translate-x-0' }`} />
                  </button>
                </td>
              </tr>
              ))}
            </tbody>
          </table>
          )}
        </div>
      </div>
      );
      })}
  </div>
  );
  };

  export default CommissionRulesManagement;