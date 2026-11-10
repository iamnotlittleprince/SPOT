import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { createBrowserSession } from './browser-fixtures.mjs';
const browser=await chromium.launch();
let checks=0;const errors=[];
try {
 for(const width of [320,1440]) {
  const {page,context,requests}=await createBrowserSession(browser,{dismissNotice:true,viewport:{width,height:900},storage:{'spot.theme':'dark','spot.reading-settings':JSON.stringify({scale:width===320?120:100,font:'verdana'})},onError:e=>errors.push(e)});
  const members=[{user_id:1,name:'Analista da equipe com nome longo',email:'analista@example.test',role:'analyst'},{user_id:2,name:'Gerente da equipe',email:'gerente@example.test',role:'manager'},{user_id:3,name:'Convidado da equipe',email:'convidado@example.test',role:'guest'},{user_id:4,name:'Outra analista',email:'outra@example.test',role:'analyst'}];
  let writes=0;
  await page.route('**/api/v1/teams/1',r=>{ if(r.request().method()!=='GET') writes++; return r.fulfill({json:{project_id:1,project_name:'Planejamento',name:'Time de Planejamento',members,can_edit:false,available_users:[{id:99,name:'Pessoa de outro projeto',email:'externa@example.test'}]}}); });
  await page.locator('.home-label > button').last().click();await page.getByRole('menuitem',{name:'Equipes',exact:true}).click();
  const list=page.locator('.team-member-list');await list.getByText(members[0].name,{exact:true}).waitFor();
  assert.equal(await list.locator('article').count(),4);checks++;
  assert.deepEqual(await list.locator('.team-member-role').allTextContents(),['Gerente','Analista','Analista','Convidado']);checks++;
  assert.deepEqual(await list.locator('.team-member-identity strong').allTextContents(),[members[1].name,members[0].name,members[3].name,members[2].name]);checks++;
  assert.equal(await list.locator('button,select,input,[aria-pressed]').count(),0);checks++;
  assert.equal(await page.locator('.team-name-field input').count(),0);checks++;
  assert.equal(await list.getByText('Pessoa de outro projeto',{exact:true}).count(),0);checks++;
  assert.ok(await list.getByText('Gerente',{exact:true}).isVisible());checks++;
  await page.getByRole('textbox',{name:'Buscar pessoa',exact:true}).fill('outro projeto');assert.equal(await list.locator('article').count(),0);checks++;
  await page.getByRole('textbox',{name:'Buscar pessoa',exact:true}).fill('');
  await page.getByRole('textbox',{name:'Buscar pessoa',exact:true}).press('Enter');
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));checks++;
  if(width===1440)await page.screenshot({path:'/tmp/spot-team-readonly.png'});
  assert.ok(requests.every(r=>r.method==='GET'));assert.equal(writes,0);checks++;
  await context.close();
 }
 assert.deepEqual(errors,[]);console.log(JSON.stringify({checks,errors}));
}finally{await browser.close();}
