import React from 'react';

export default function LanguageSwitcher({locale,onChange,labelledBy}) {
  return <div className="language-switcher" role="group" aria-label={labelledBy ? undefined : "Idioma"} aria-labelledby={labelledBy}>
    {[['pt','🇧🇷','Português'],['en','🇺🇸','English'],['es','🇪🇸','Español']].map(([code,flag,label])=>
      <button type="button" key={code} className={locale===code?'active':''} title={label} aria-label={label} aria-pressed={locale===code} onClick={()=>onChange(code)}>
        <span aria-hidden="true">{flag}</span>
      </button>)}
  </div>;
}
