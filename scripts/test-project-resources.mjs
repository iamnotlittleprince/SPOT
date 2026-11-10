import assert from 'node:assert/strict';
import {chromium} from 'playwright';
import {createBrowserSession} from './browser-fixtures.mjs';
const browser=await chromium.launch();const errors=[];
try {
for(const width of [320,1440]) for(const theme of ['light','dark']) {
const {page,context}=await createBrowserSession(browser,{dismissNotice:true,viewport:{width,height:1000},storage:{'spot.theme':theme,'spot.reading-settings':JSON.stringify({scale:120,font:'verdana'})},onError:e=>errors.push(e)});
const long='Implementação e acompanhamento do projeto com nome extenso para validação da leitura';
const projects=[{id:1,name:long,client_name:'Cliente com nome extenso para conferir responsividade',status:'planning'},{id:2,name:'Outro projeto',status:'planning'}];
let failed=true;
await page.route('**/api/v1/projects',route=>route.fulfill({json:projects}));
await page.route('**/api/v1/management/access',route=>route.fulfill({json:{}}));
await page.route('**/api/v1/projects/*/expenses',route=>route.fulfill({json:[]}));
await page.route('**/api/v1/projects/*/resources',route=>{
if(route.request().url().includes('/2/')) return route.fulfill({json:{tasks:[],documents:[]}});
if(failed) {return route.fulfill({status:503,json:{message:'Não foi possível carregar tarefas e documentos do projeto.'}});}
return route.fulfill({json:{tasks:[{id:1,title:'Preparar apresentação do projeto',description:'Revisar os documentos com a equipe.',status:'done',due_date:'2026-11-12',user:{name:'Responsável com nome bastante extenso para leitura'}},{id:2,title:'Validar implantação',status:'in_progress',user:{name:'Ana'}}],documents:[{id:3,name:'Relatório de implementação com nome extenso e informações detalhadas.pdf',size:2048,created_at:'2026-11-09T12:00:00Z'},{id:4,name:'Anexo.xlsx',size:1024,created_at:'2026-11-09T12:00:00Z'}]}});
});
await page.goto('http://localhost:8000/');
await page.locator('.home-label > button').last().click();
await page.getByRole('menuitem',{name:'Projetos',exact:true}).click();
await page.getByRole('button',{name:long,exact:true}).click();
const detail=page.locator('.management-project-detail');
await detail.getByRole('alert').waitFor();
failed=false;
await detail.getByRole('button',{name:'Tentar novamente',exact:true}).click();
await detail.getByText('Preparar apresentação do projeto',{exact:true}).waitFor();
assert.equal(await detail.locator('h3#project-tasks-title').count(),1);
assert.equal(await detail.locator('h3#project-documents-title').count(),1);
await detail.getByText('1 de 2 concluídas',{exact:true}).waitFor();
const summary=detail.locator('summary').filter({hasText:'Descrição da tarefa'});
await summary.focus();await page.keyboard.press('Enter');
await detail.getByText('Revisar os documentos com a equipe.',{exact:true}).waitFor();
await detail.getByRole('searchbox',{name:'Buscar tarefa no projeto',exact:true}).fill('Validar');
assert.equal(await detail.getByText('Preparar apresentação do projeto',{exact:true}).count(),0);
await detail.getByRole('searchbox',{name:'Buscar tarefa no projeto',exact:true}).fill('');
const download=detail.getByRole('link',{name:'Baixar Relatório de implementação com nome extenso e informações detalhadas.pdf',exact:true});
assert.equal(await download.getAttribute('href'),'/api/v1/documents/3/download');
await download.focus();assert.equal(await download.evaluate(el=>document.activeElement===el),true);
await detail.getByRole('searchbox',{name:'Buscar documento no projeto',exact:true}).fill('não existe');
await detail.getByText('Nenhum documento corresponde à busca.',{exact:true}).waitFor();
await detail.getByRole('searchbox',{name:'Buscar documento no projeto',exact:true}).fill('');
assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=document.documentElement.clientWidth+1),true);
for(const locator of [detail,detail.locator('.project-resource-card').first(),detail.locator('.project-resource-card').last()]) {
const bounds=await locator.boundingBox();assert.ok(bounds.x>=0&&bounds.x+bounds.width<=width+1);
}
await detail.scrollIntoViewIfNeeded();
await page.screenshot({path:'/tmp/spot-project-resources-'+width+'-'+theme+'.png',fullPage:true});
await detail.getByRole('button',{name:'Fechar',exact:true}).click();
await page.getByRole('button',{name:'Outro projeto',exact:true}).click();
await detail.getByText('Nenhuma tarefa vinculada ao projeto.',{exact:true}).waitFor();
await detail.getByText('Nenhum documento vinculado ao projeto.',{exact:true}).waitFor();
assert.equal(await detail.getByText('Preparar apresentação do projeto',{exact:true}).count(),0);
await context.close();
}
assert.deepEqual(errors,[]);
console.log('Projeto: tarefas, documentos, permissões simuladas, buscas, erro/repetição, teclado e 320/1440px, claro/escuro, leitura 120% passaram.');
} finally {await browser.close();}
