import React from 'react';
import brazilFlag from '../images/flags/br.svg';
import unitedStatesFlag from '../images/flags/us.svg';
import spainFlag from '../images/flags/es.svg';

export default function LanguageSwitcher({locale,onChange,labelledBy}) {
  return <div className="language-switcher" role="group" aria-label={labelledBy ? undefined : "Idioma"} aria-labelledby={labelledBy}>
    {[['pt',brazilFlag,'Português'],['en',unitedStatesFlag,'English'],['es',spainFlag,'Español']].map(([code,flag,label])=>
      <button type="button" key={code} className={locale===code?'active':''} title={label} aria-label={label} aria-pressed={locale===code} onClick={()=>onChange(code)}>
        <img className="language-flag" src={flag} alt="" aria-hidden="true" width="22" height="16" draggable="false" />
      </button>)}
  </div>;
}
