from pathlib import Path
helper=Path(__file__).with_name('atualizar_diferenciais.py')
exec(compile(helper.read_text().split("s=base(8,")[0],str(helper),'exec'))
s=base(10,'10 / FLUXO E TELAS','Do acesso ao resultado: o fluxo principal','Telas do Spot e a jornada central de gestão de projetos; recursos disponíveis conforme as permissões.')
labels=['1  Acesso','2  Projeto','3  Equipe e tarifas','4  Tarefas e horas','5  Despesas','6  Resultado']
for i,title in enumerate(labels):
 x=.6+i*2.06
 box(s,x,2.19,1.84,.76,NAVY,True)
 txt(s,x+.10,2.37,1.64,.48,title,14,WHITE,True)
 if i<5:txt(s,x+1.87,2.41,.19,.3,'→',15,TEAL,True)
txt(s,.6,3.09,12.1,.28,'Entrar → cadastrar/configurar → atribuir responsáveis → registrar execução → aprovar custos → acompanhar.',12,GRAY)
groups=[('Acesso e conta','Login\nPrimeiro acesso / ativação\nAceitar convite\nRecuperar senha\nRedefinir senha\nMeu perfil'),('Trabalho diário','Home\nMeus Projetos\nNovo Projeto\nEquipes\nMinhas tarefas\nMeus documentos'),('Gestão e controle','Caixa de entrada\nAgenda\nPortfólios\nClientes\nHistórico\nParâmetros\nUsuários e responsáveis')]
for i,(title,body) in enumerate(groups):
 x=.6+i*4.12
 box(s,x,3.60,3.88,2.63,WHITE,True);box(s,x,3.60,.045,2.63,TEAL)
 txt(s,x+.17,3.78,3.54,.4,title,18,TEAL,True)
 body_shape=txt(s,x+.17,4.30,3.54,1.86,body,14)
 for para in body_shape.text_frame.paragraphs:
  para.space_after=Pt(2);para.line_spacing=1.05
banner(s,'No projeto: configuração, tarifas, impostos, despesas, resultado financeiro e auditoria; tarefas em lista/quadro.')
s.notes_slide.notes_text_frame.text='''SLIDE 11 — TEMPO: 75 segundos
Acumulado: 09:15 a 10:30

O fluxo central começa pelo acesso: o usuário entra com sua conta, ativa o primeiro acesso ou aceita um convite. Conforme as permissões, cadastra o cliente e o projeto, configura equipe, tarifas e impostos. A equipe registra tarefas, horas e andamento; as despesas são lançadas e revisadas. Esses registros alimentam o resultado financeiro do projeto e os indicadores do portfólio. Agenda e caixa de entrada apoiam os prazos e a comunicação; documentos e histórico apoiam a colaboração e a rastreabilidade. O projeto também pode ser finalizado ou reaberto com justificativa, conforme as regras de acesso.

As telas estão listadas no slide. As áreas autenticadas são: Home, Caixa de entrada, Portfólios, Agenda, Meus Projetos, Equipes, Clientes, Novo Projeto, Minhas tarefas, Meus documentos, Histórico, Meu perfil, Parâmetros e Usuários e responsáveis. O acesso inclui Login, Primeiro acesso/ativação, Aceitar convite, Recuperar senha e Redefinir senha. Meu perfil reúne dados pessoais, segurança, notificações e preferências. Dentro do projeto há configuração, integrantes, tarifas, impostos, despesas, resultado e auditoria; são seções e formulários, não novas entradas no menu. Parâmetros reúne clientes, status e situações do projeto, tipos de atividade, tipos de despesa, impostos e configurações administrativas. A criação de convites é um diálogo. Autenticação em dois fatores e integrações aparecem nos fluxos e configurações correspondentes. Os atalhos de aplicativos externos não equivalem a telas internas. As opções visíveis dependem das permissões.

Para concluir, o Spot conecta organização da execução, controle de acesso e visão do resultado gerencial, alinhados às necessidades levantadas na pesquisa. Obrigado.'''
r.save(p)
print('Slide 11 atualizado; demais slides preservados.')
