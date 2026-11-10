import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
const exec = promisify(execFile);
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const output = path.resolve(root, process.argv[2] || 'output/production-review/browser-final');
await fs.mkdir(output, {recursive:true});
const pending = (await fs.readdir(path.join(root,'scripts'))).filter(name => /^test-.*\.mjs$/.test(name)).sort();
const results = [];
async function worker() {
  for (let file; (file = pending.shift());) {
    const started = Date.now();
    let code = 0, log = '';
    try { const result = await exec(process.execPath, [path.join(root,'scripts',file)], {cwd:root,timeout:240000,maxBuffer:4*1024*1024}); log=result.stdout+result.stderr; }
    catch(error) { code=Number(error.code)||1;log=(error.stdout||'')+(error.stderr||'')+'\n'+error.message; }
    await fs.writeFile(path.join(output,file.replace('.mjs','.log')),log);
    const result={script:file,exit_code:code,seconds:Number(((Date.now()-started)/1000).toFixed(1))};
    results.push(result);console.log(JSON.stringify(result));
    await fs.writeFile(path.join(output,'results.json'),JSON.stringify(results,null,2));
  }
}
await Promise.all([worker(),worker()]);
const failed=results.filter(result=>result.exit_code!==0);
console.log(JSON.stringify({suites:results.length,passed:results.length-failed.length,failed:failed.map(result=>result.script)}));
process.exitCode=failed.length?1:0;
