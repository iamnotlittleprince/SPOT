import React, { useCallback, useEffect, useState } from "react";
import { apiJson } from "./api";

const currency = new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" });

function Auth({ onAuthenticated }) {
  const [form, setForm] = useState({ email: "", password: "" });
  const [error, setError] = useState("");
  const submit = async (event) => {
    event.preventDefault(); setError("");
    try {
      const data = await apiJson(`/auth/login`, { method: "POST", body: JSON.stringify(form) });
      onAuthenticated(data.user);
    } catch (err) { setError(err.message); }
  };
  return <main className="inventory-auth"><section><span className="inventory-kicker">SPOT V2</span><h1>Controle de estoque</h1><p>Entre para registrar produtos e movimentações com segurança.</p><form onSubmit={submit}>
    <label>E-mail<input required type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} /></label>
    <label>Senha<input required type="password" minLength="8" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} /></label>
    {error && <p className="inventory-error">{error}</p>}<button>Entrar</button>
  </form><a className="inventory-link" href="/first-access">Primeiro acesso</a></section></main>;
}

export default function InventoryApp() {
  const [authenticated, setAuthenticated] = useState(() => Boolean(window.__SPOT__?.authenticated));
  const [user, setUser] = useState(() => window.__SPOT__?.user || null);
  const [data, setData] = useState({ dashboard: null, categories: [], products: [], movements: [] });
  const [message, setMessage] = useState("");
  const load = useCallback(async () => {
    if (!authenticated) return;
    try {
      const [dashboard, categories, products, movements] = await Promise.all(["/dashboard", "/categories", "/products", "/movements"].map((path) => apiJson(path)));
      setData({ dashboard, categories, products, movements });
    } catch (err) { setMessage(err.message); if (/token|autentic|unauthor/i.test(err.message)) setAuthenticated(false); }
  }, [authenticated]);
  useEffect(() => { load(); }, [load]);
  const authenticate = (newUser) => { setAuthenticated(true); setUser(newUser); };
  const submit = async (path, payload) => { try { await apiJson(path, { method: "POST", body: JSON.stringify(payload) }); setMessage("Registro salvo com sucesso."); await load(); } catch (err) { setMessage(err.message); } };
  if (!authenticated) return <Auth onAuthenticated={authenticate} />;
  const dashboard = data.dashboard || {};
  return <main className="inventory-shell"><header className="inventory-header"><div><span className="inventory-kicker">SPOT V2 · API segura</span><h1>Estoque</h1></div><div><span>Olá, {user?.name}</span><button onClick={async () => { await apiJson('/auth/logout', { method: 'POST' }); setAuthenticated(false); setUser(null); }}>Sair</button></div></header>
    {message && <p className="inventory-notice">{message}</p>}
    <section className="inventory-stats"><article><small>Produtos</small><strong>{dashboard.total_products ?? "–"}</strong></article><article><small>Categorias</small><strong>{dashboard.total_categories ?? "–"}</strong></article><article><small>Unidades em estoque</small><strong>{dashboard.total_stock_units ?? "–"}</strong></article><article><small>Valor em estoque</small><strong>{currency.format(dashboard.total_stock_value || 0)}</strong></article></section>
    <section className="inventory-grid"><FormCard title="Nova categoria" onSubmit={(form) => submit("/categories", form)} fields={[['name', 'Nome'], ['description', 'Descrição (opcional)']]} /><FormCard title="Novo produto" onSubmit={(form) => submit("/products", { ...form, category_id: Number(form.category_id), price: Number(form.price), quantity: Number(form.quantity || 0) })} fields={[['name', 'Nome'], ['sku', 'SKU'], ['price', 'Preço', 'number'], ['quantity', 'Quantidade inicial', 'number']]} select={{ name: 'category_id', label: 'Categoria', options: data.categories }} /><FormCard title="Movimentar estoque" onSubmit={(form) => submit("/movements", { ...form, product_id: Number(form.product_id), quantity: Number(form.quantity) })} fields={[['quantity', 'Quantidade', 'number'], ['note', 'Observação (opcional)']]} select={{ name: 'product_id', label: 'Produto', options: data.products }} movement /></section>
    <section className="inventory-table-card"><h2>Produtos</h2><div className="inventory-table"><div className="inventory-row inventory-table-head"><span>Produto</span><span>Categoria</span><span>SKU</span><span>Preço</span><span>Saldo</span></div>{data.products.map((product) => <div className="inventory-row" key={product.id}><strong>{product.name}</strong><span>{product.category?.name}</span><span>{product.sku}</span><span>{currency.format(product.price)}</span><b>{product.quantity}</b></div>)}{!data.products.length && <p>Nenhum produto cadastrado.</p>}</div></section>
  </main>;
}

function FormCard({ title, fields, select, movement, onSubmit }) {
  const [form, setForm] = useState({ type: "in" });
  return <form className="inventory-form" onSubmit={(event) => { event.preventDefault(); onSubmit(form); event.currentTarget.reset(); setForm({ type: "in" }); }}><h2>{title}</h2>{select && <label>{select.label}<select required value={form[select.name] || ""} onChange={(e) => setForm({ ...form, [select.name]: e.target.value })}><option value="">Selecione</option>{select.options.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>}{movement && <label>Tipo<select value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })}><option value="in">Entrada</option><option value="out">Saída</option></select></label>}{fields.map(([name, label, type]) => <label key={name}>{label}<input required={!label.includes("opcional")} type={type || "text"} min={type === "number" ? "0" : undefined} step={name === "price" ? "0.01" : undefined} value={form[name] || ""} onChange={(e) => setForm({ ...form, [name]: e.target.value })} /></label>)}<button>Salvar</button></form>;
}
