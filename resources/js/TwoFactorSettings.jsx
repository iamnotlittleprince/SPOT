import React, { useRef, useState } from 'react';
import { ShieldCheck } from 'lucide-react';
import { sessionRequest } from './api';

export default function TwoFactorSettings({ enabled, onUpdate }) {
  const [step, setStep] = useState('idle');
  const [setup, setSetup] = useState(null);
  const [codes, setCodes] = useState([]);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const trigger = useRef(null);
  const codeInput = useRef(null);
  async function submit(event) {
    event.preventDefault();
    if (busy) return;
    const form = event.currentTarget;
    const data = Object.fromEntries(new FormData(form));
    const action = step;
    setBusy(true); setError(''); setNotice('');
    try {
      const path = action === 'confirm' ? '/confirm' : action === 'password' ? '/setup' : '';
      const response = await sessionRequest(`/security/two-factor${path}`, {
        method: action === 'disable' ? 'DELETE' : 'POST', body: JSON.stringify(data),
      });
      const result = await response.json();
      if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message);
      form.reset();
      if (action === 'password') {
        setSetup(result); setStep('confirm');
        requestAnimationFrame(() => codeInput.current?.focus());
      } else {
        setSetup(null); setStep('idle');
        setCodes(result.recovery_codes || []);
        setNotice(action === 'confirm' ? '2FA ativado' : '2FA desativado');
        // Keep successful activation and recovery codes visible even if refreshing fails.
        try { await onUpdate(); } catch { setError('Não foi possível carregar a segurança da conta.'); }
        trigger.current?.focus();
      }
    } catch (failure) { setError(failure.message || 'Não foi possível concluir a operação.'); }
    finally { setBusy(false); }
  }
  function cancel() {
    setStep('idle'); setSetup(null); setError('');
    requestAnimationFrame(() => trigger.current?.focus());
  }
  return <section className="security-card two-factor-card" aria-labelledby="two-factor-title">
    <header><span><ShieldCheck size={18} aria-hidden="true" /><strong id="two-factor-title">Autenticação em dois fatores</strong></span><em className={enabled ? 'enabled' : ''}>{enabled ? 'Ativa' : 'Desativada'}</em></header>
    <p>Use um aplicativo autenticador para gerar um código adicional no login.</p>
    {step === 'idle' && <button ref={trigger} className="secondary-button" type="button" onClick={() => { setStep(enabled ? 'disable' : 'password'); setError(''); setNotice(''); }}>{enabled ? 'Desativar 2FA' : 'Configurar 2FA'}</button>}
    {step !== 'idle' && <form className="two-factor-form" onSubmit={submit} aria-busy={busy}>
      {step === 'confirm' ? <>
        <div className="two-factor-setup"><img src={setup.qr_code} alt="QR Code para configurar autenticação em dois fatores" /><div><p>No aplicativo autenticador, escolha adicionar conta e escanear QR Code.</p><p>Se a leitura não funcionar, adicione uma conta manualmente: nome Spot, tipo baseado em tempo (TOTP) e a chave abaixo.</p><code translate="no">{setup.secret}</code><p>Depois, digite o código exibido no aplicativo para confirmar a ativação.</p></div></div>
        <label htmlFor="two-factor-code">Código de autenticação</label>
        <input ref={codeInput} id="two-factor-code" name="code" inputMode="numeric" autoComplete="one-time-code" pattern="[0-9]{6}" maxLength={6} required placeholder="Código de 6 dígitos" aria-invalid={error ? true : undefined} aria-describedby={error ? 'two-factor-error' : undefined} />
      </> : <>
        <label htmlFor="two-factor-password">Senha atual</label>
        <input key={step} autoFocus id="two-factor-password" name="password" type="password" autoComplete="current-password" required aria-invalid={error ? true : undefined} aria-describedby={error ? 'two-factor-error' : undefined} />
      </>}
      <div className="two-factor-actions"><button className="primary-action" type="submit" disabled={busy}>{busy ? 'Aguarde...' : step === 'confirm' ? 'Confirmar ativação' : step === 'disable' ? 'Desativar 2FA' : 'Continuar'}</button><button className="secondary-button" type="button" disabled={busy} onClick={cancel}>Cancelar</button></div>
    </form>}
    {error && <p id="two-factor-error" className="security-feedback error" role="alert">{error}</p>}
    <p role="status">{notice}</p>
    {!!codes.length && <div className="recovery-codes"><strong>Salve estes códigos de recuperação agora:</strong><p>Cada código pode ser usado uma única vez se você perder o acesso ao autenticador. Guarde-os em um local seguro.</p>{codes.map(code => <code translate="no" key={code}>{code}</code>)}</div>}
  </section>;
}
