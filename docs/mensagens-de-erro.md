# Mensagens de erro do Spot

Catálogo gerado a partir dos textos do código e dos arquivos de idioma. Reproduzir com `./php scripts/catalog-error-messages.php`. Não consulta nem altera o banco.

## Como os erros são apresentados

- Validação: resposta HTTP 422 com resumo em `message` e mensagens específicas em `errors`, por campo. O editor de projetos e cadastros associa as mensagens com `aria-describedby` e marca os campos com `aria-invalid`; preserva os valores e anuncia o resumo sem deslocar o foco.
- Duplicidade de proposta: **Este número de proposta já está em uso, inclusive em projetos arquivados. Informe outro número.** A reserva de números dos projetos arquivados continua em vigor.
- Regras de negócio: preservar a explicação específica do fluxo. Os textos encontrados no código estão no inventário abaixo.
- Falhas HTTP: mensagens padrão identificam acesso, sessão, limite de tentativas, item indisponível ou falha do serviço e orientam o próximo passo. Detalhes internos de falhas 5xx não são retornados.
- Rede: explicar a falha de conexão e orientar a conferir o resultado antes de repetir uma operação. Cancelamentos intencionais não são tratados como falha de rede.
- Idiomas: catálogos comuns de validação, autenticação, recuperação de senha e HTTP em pt/en/es. O cliente envia `X-Spot-Locale` com a escolha da interface. Mensagens específicas já existentes mantêm suas traduções existentes; o inventário não certifica tradução integral de todo texto dinâmico.

## Validações comuns

Os marcadores `:attribute`, `:min`, `:max`, `:date` e similares são substituídos pelo campo e pelos limites da regra. Arquivos usam KB nos limites de validação. Nomes de pessoas e conteúdo de usuários não são traduzidos.

| Regra | Português | English | Español |
| --- | --- | --- | --- |
| accepted | Aceite :attribute para continuar. | Accept :attribute to continue. | Acepte :attribute para continuar. |
| accepted_if | Aceite :attribute quando :other for :value. | Accept :attribute when :other is :value. | Acepte :attribute cuando :other sea :value. |
| active_url | Informe um endereço válido em :attribute. | Enter a valid address in :attribute. | Introduzca una dirección válida en :attribute. |
| after | Informe :attribute posterior a :date. | Enter :attribute after :date. | Introduzca :attribute posterior a :date. |
| after_or_equal | Informe :attribute igual ou posterior a :date. | Enter :attribute on or after :date. | Introduzca :attribute igual o posterior a :date. |
| alpha | Use apenas letras em :attribute. | Use only letters in :attribute. | Use solo letras en :attribute. |
| alpha_dash | Use apenas letras, números, hífens e sublinhados em :attribute. | Use only letters, numbers, dashes and underscores in :attribute. | Use solo letras, números, guiones y guiones bajos en :attribute. |
| alpha_num | Use apenas letras e números em :attribute. | Use only letters and numbers in :attribute. | Use solo letras y números en :attribute. |
| any_of | Confira :attribute e informe uma das opções aceitas. | Check :attribute and enter an accepted option. | Revise :attribute e introduzca una opción permitida. |
| array | Selecione uma lista válida para :attribute. | Select a valid list for :attribute. | Seleccione una lista válida para :attribute. |
| array_keys | Use somente estas opções em :attribute: :values. | Use only these options in :attribute: :values. | Use solo estas opciones en :attribute: :values. |
| ascii | Use somente caracteres ASCII em :attribute. | Use only ASCII characters in :attribute. | Use solo caracteres ASCII en :attribute. |
| base64 | Informe um conteúdo Base64 válido em :attribute. | Enter valid Base64 content in :attribute. | Introduzca contenido Base64 válido en :attribute. |
| before | Informe :attribute anterior a :date. | Enter :attribute before :date. | Introduzca :attribute anterior a :date. |
| before_or_equal | Informe :attribute igual ou anterior a :date. | Enter :attribute on or before :date. | Introduzca :attribute igual o anterior a :date. |
| boolean | Selecione sim ou não em :attribute. | Select yes or no for :attribute. | Seleccione sí o no en :attribute. |
| can | Sua conta não pode usar esse valor em :attribute. Escolha uma opção permitida. | Your account cannot use this value in :attribute. Choose an allowed option. | Su cuenta no puede usar este valor en :attribute. Elija una opción permitida. |
| confirmed | A confirmação de :attribute não coincide. Digite o mesmo valor nos dois campos. | The confirmation of :attribute does not match. Enter the same value in both fields. | La confirmación de :attribute no coincide. Introduzca el mismo valor en ambos campos. |
| contains | Inclua em :attribute os valores obrigatórios: :values. | Include the required values in :attribute: :values. | Incluya en :attribute los valores obligatorios: :values. |
| current_password | A senha atual está incorreta. Confira e tente novamente. | The current password is incorrect. Check it and try again. | La contraseña actual es incorrecta. Revísela e inténtelo de nuevo. |
| date | Informe uma data válida em :attribute. | Enter a valid date in :attribute. | Introduzca una fecha válida en :attribute. |
| date_equals | Informe :date em :attribute. | Enter :date in :attribute. | Introduzca :date en :attribute. |
| date_format | Use o formato :format em :attribute. | Use the :format format in :attribute. | Use el formato :format en :attribute. |
| decimal | Use :decimal casas decimais em :attribute. | Use :decimal decimal places in :attribute. | Use :decimal decimales en :attribute. |
| declined | Recuse :attribute para continuar. | Decline :attribute to continue. | Rechace :attribute para continuar. |
| declined_if | Recuse :attribute quando :other for :value. | Decline :attribute when :other is :value. | Rechace :attribute cuando :other sea :value. |
| different | Informe em :attribute um valor diferente de :other. | Enter a value in :attribute different from :other. | Introduzca en :attribute un valor distinto de :other. |
| digits | Informe exatamente :digits dígitos em :attribute. | Enter exactly :digits digits in :attribute. | Introduzca exactamente :digits dígitos en :attribute. |
| digits_between | Use entre :min e :max dígitos em :attribute. | Use between :min and :max digits in :attribute. | Use entre :min y :max dígitos en :attribute. |
| dimensions | As dimensões da imagem em :attribute não são aceitas. Escolha outra imagem conforme os limites do campo. | The image dimensions in :attribute are not accepted. Choose another image within the field limits. | Las dimensiones de la imagen en :attribute no se admiten. Elija otra imagen dentro de los límites del campo. |
| distinct | Há valores repetidos em :attribute. Remova as repetições. | There are duplicate values in :attribute. Remove the duplicates. | Hay valores repetidos en :attribute. Elimine los duplicados. |
| doesnt_contain | Remova de :attribute estes valores: :values. | Remove these values from :attribute: :values. | Elimine de :attribute estos valores: :values. |
| doesnt_end_with | Não termine :attribute com: :values. | Do not end :attribute with: :values. | No termine :attribute con: :values. |
| doesnt_start_with | Não inicie :attribute com: :values. | Do not start :attribute with: :values. | No comience :attribute con: :values. |
| email | Informe um endereço de e-mail válido em :attribute. | Enter a valid email address in :attribute. | Introduzca una dirección de correo válida en :attribute. |
| encoding | Use a codificação :encoding em :attribute. | Use :encoding encoding in :attribute. | Use la codificación :encoding en :attribute. |
| ends_with | Termine :attribute com uma destas opções: :values. | End :attribute with one of these options: :values. | Termine :attribute con una de estas opciones: :values. |
| enum | Escolha uma opção válida para :attribute. | Choose a valid option for :attribute. | Elija una opción válida para :attribute. |
| exists | A opção de :attribute não está disponível. Atualize a lista e selecione um cadastro válido. | The option for :attribute is unavailable. Refresh the list and select a valid record. | La opción de :attribute no está disponible. Actualice la lista y seleccione un registro válido. |
| extensions | Envie em :attribute um arquivo com uma destas extensões: :values. | Upload a file in :attribute with one of these extensions: :values. | Envíe en :attribute un archivo con una de estas extensiones: :values. |
| file | Selecione um arquivo válido para :attribute. | Select a valid file for :attribute. | Seleccione un archivo válido para :attribute. |
| filled | Preencha :attribute ou remova esse campo do envio. | Fill in :attribute or remove this field from the request. | Complete :attribute o elimine este campo del envío. |
| hex_color | Informe uma cor hexadecimal válida em :attribute, como #005A80. | Enter a valid hexadecimal color in :attribute, such as #005A80. | Introduzca un color hexadecimal válido en :attribute, como #005A80. |
| image | Escolha um arquivo de imagem válido para :attribute. | Choose a valid image file for :attribute. | Elija un archivo de imagen válido para :attribute. |
| in | Escolha uma das opções disponíveis para :attribute. | Choose an available option for :attribute. | Elija una opción disponible para :attribute. |
| in_array | Escolha para :attribute um valor presente em :other. | Choose a value for :attribute that is present in :other. | Elija para :attribute un valor presente en :other. |
| in_array_keys | Inclua em :attribute pelo menos uma destas opções: :values. | Include at least one of these options in :attribute: :values. | Incluya en :attribute al menos una de estas opciones: :values. |
| integer | Informe um número inteiro em :attribute. | Enter a whole number in :attribute. | Introduzca un número entero en :attribute. |
| ip | Informe um endereço IP válido em :attribute. | Enter a valid IP address in :attribute. | Introduzca una dirección IP válida en :attribute. |
| ipv4 | Informe um endereço IPv4 válido em :attribute. | Enter a valid IPv4 address in :attribute. | Introduzca una dirección IPv4 válida en :attribute. |
| ipv6 | Informe um endereço IPv6 válido em :attribute. | Enter a valid IPv6 address in :attribute. | Introduzca una dirección IPv6 válida en :attribute. |
| json | Informe conteúdo JSON válido em :attribute. | Enter valid JSON content in :attribute. | Introduzca contenido JSON válido en :attribute. |
| list | Envie :attribute como uma lista ordenada de itens. | Submit :attribute as an ordered list of items. | Envíe :attribute como una lista ordenada de elementos. |
| lowercase | Use letras minúsculas em :attribute. | Use lowercase letters in :attribute. | Use letras minúsculas en :attribute. |
| mac_address | Informe um endereço MAC válido em :attribute. | Enter a valid MAC address in :attribute. | Introduzca una dirección MAC válida en :attribute. |
| max_digits | Use no máximo :max dígitos em :attribute. | Use at most :max digits in :attribute. | Use como máximo :max dígitos en :attribute. |
| mimes | Escolha para :attribute um arquivo de um destes tipos: :values. | Choose a file for :attribute of one of these types: :values. | Elija para :attribute un archivo de uno de estos tipos: :values. |
| mimetypes | Escolha para :attribute um arquivo de um destes tipos: :values. | Choose a file for :attribute of one of these types: :values. | Elija para :attribute un archivo de uno de estos tipos: :values. |
| min_digits | Use pelo menos :min dígitos em :attribute. | Use at least :min digits in :attribute. | Use al menos :min dígitos en :attribute. |
| missing | Remova :attribute do envio. | Remove :attribute from the request. | Elimine :attribute del envío. |
| missing_if | Remova :attribute quando :other for :value. | Remove :attribute when :other is :value. | Elimine :attribute cuando :other sea :value. |
| missing_unless | Envie :attribute somente quando :other for :value. | Submit :attribute only when :other is :value. | Envíe :attribute solo cuando :other sea :value. |
| missing_with | Remova :attribute quando :values estiver presente. | Remove :attribute when :values is present. | Elimine :attribute cuando :values esté presente. |
| missing_with_all | Remova :attribute quando todos estes campos estiverem presentes: :values. | Remove :attribute when all these fields are present: :values. | Elimine :attribute cuando todos estos campos estén presentes: :values. |
| multiple_of | Informe em :attribute um múltiplo de :value. | Enter a multiple of :value in :attribute. | Introduzca en :attribute un múltiplo de :value. |
| not_in | Escolha outro valor para :attribute. Este valor não é permitido. | Choose another value for :attribute. This value is not allowed. | Elija otro valor para :attribute. Este valor no está permitido. |
| not_regex | Confira o formato de :attribute e use o formato indicado no campo. | Check the format of :attribute and use the format shown by the field. | Revise el formato de :attribute y use el formato indicado en el campo. |
| numeric | Informe um número válido em :attribute. | Enter a valid number in :attribute. | Introduzca un número válido en :attribute. |
| present | Inclua :attribute no envio, mesmo que esteja vazio. | Include :attribute in the request, even if empty. | Incluya :attribute en el envío, aunque esté vacío. |
| present_if | Inclua :attribute quando :other for :value. | Include :attribute when :other is :value. | Incluya :attribute cuando :other sea :value. |
| present_unless | Inclua :attribute, exceto quando :other for :value. | Include :attribute unless :other is :value. | Incluya :attribute, excepto cuando :other sea :value. |
| present_with | Inclua :attribute quando :values estiver presente. | Include :attribute when :values is present. | Incluya :attribute cuando :values esté presente. |
| present_with_all | Inclua :attribute quando todos estes campos estiverem presentes: :values. | Include :attribute when all these fields are present: :values. | Incluya :attribute cuando todos estos campos estén presentes: :values. |
| prohibited | Não é permitido alterar :attribute nesta operação. Remova o campo do envio. | Changing :attribute is not allowed in this operation. Remove the field from the request. | No se permite cambiar :attribute en esta operación. Elimine el campo del envío. |
| prohibited_if | Remova :attribute quando :other for :value. | Remove :attribute when :other is :value. | Elimine :attribute cuando :other sea :value. |
| prohibited_if_accepted | Remova :attribute quando :other for aceito. | Remove :attribute when :other is accepted. | Elimine :attribute cuando se acepte :other. |
| prohibited_if_declined | Remova :attribute quando :other for recusado. | Remove :attribute when :other is declined. | Elimine :attribute cuando se rechace :other. |
| prohibited_unless | Envie :attribute somente quando :other for uma destas opções: :values. | Submit :attribute only when :other is one of these options: :values. | Envíe :attribute solo cuando :other sea una de estas opciones: :values. |
| prohibits | Não envie :attribute junto com :other. | Do not submit :attribute together with :other. | No envíe :attribute junto con :other. |
| regex | Confira o formato de :attribute e use o formato indicado no campo. | Check the format of :attribute and use the format shown by the field. | Revise el formato de :attribute y use el formato indicado en el campo. |
| required | Preencha :attribute para continuar. | Fill in :attribute to continue. | Complete :attribute para continuar. |
| required_array_keys | Inclua em :attribute estas informações obrigatórias: :values. | Include this required information in :attribute: :values. | Incluya en :attribute esta información obligatoria: :values. |
| required_if | Preencha :attribute quando :other for :value. | Fill in :attribute when :other is :value. | Complete :attribute cuando :other sea :value. |
| required_if_accepted | Preencha :attribute quando :other for aceito. | Fill in :attribute when :other is accepted. | Complete :attribute cuando se acepte :other. |
| required_if_declined | Preencha :attribute quando :other for recusado. | Fill in :attribute when :other is declined. | Complete :attribute cuando se rechace :other. |
| required_unless | Preencha :attribute, exceto quando :other for uma destas opções: :values. | Fill in :attribute unless :other is one of these options: :values. | Complete :attribute, excepto cuando :other sea una de estas opciones: :values. |
| required_with | Preencha :attribute quando :values estiver presente. | Fill in :attribute when :values is present. | Complete :attribute cuando :values esté presente. |
| required_with_all | Preencha :attribute quando todos estes campos estiverem presentes: :values. | Fill in :attribute when all these fields are present: :values. | Complete :attribute cuando todos estos campos estén presentes: :values. |
| required_without | Preencha :attribute quando :values não estiver presente. | Fill in :attribute when :values is not present. | Complete :attribute cuando :values no esté presente. |
| required_without_all | Preencha :attribute quando nenhum destes campos estiver presente: :values. | Fill in :attribute when none of these fields are present: :values. | Complete :attribute cuando ninguno de estos campos esté presente: :values. |
| same | Informe em :attribute o mesmo valor de :other. | Enter the same value in :attribute as in :other. | Introduzca en :attribute el mismo valor de :other. |
| starts_with | Inicie :attribute com uma destas opções: :values. | Start :attribute with one of these options: :values. | Comience :attribute con una de estas opciones: :values. |
| string | Informe um texto válido em :attribute. | Enter valid text in :attribute. | Introduzca un texto válido en :attribute. |
| timezone | Selecione um fuso horário válido em :attribute. | Select a valid time zone for :attribute. | Seleccione una zona horaria válida en :attribute. |
| unique | Já existe um cadastro com esse valor de :attribute. Informe outro valor. | A record already uses this value for :attribute. Enter another value. | Ya existe un registro con este valor de :attribute. Introduzca otro valor. |
| uploaded | Não foi possível enviar :attribute. Confira o tamanho do arquivo e sua conexão antes de tentar novamente. | Could not upload :attribute. Check the file size and your connection before trying again. | No se pudo enviar :attribute. Revise el tamaño del archivo y su conexión antes de intentarlo de nuevo. |
| uppercase | Use letras maiúsculas em :attribute. | Use uppercase letters in :attribute. | Use letras mayúsculas en :attribute. |
| url | Informe uma URL válida em :attribute, incluindo https://. | Enter a valid URL in :attribute, including https://. | Introduzca una URL válida en :attribute, incluyendo https://. |
| ulid | Informe um identificador ULID válido em :attribute. | Enter a valid ULID identifier in :attribute. | Introduzca un identificador ULID válido en :attribute. |
| uuid | Informe um identificador UUID válido em :attribute. | Enter a valid UUID identifier in :attribute. | Introduzca un identificador UUID válido en :attribute. |
| between.array | Use entre :min e :max itens em :attribute. | Use between :min and :max items in :attribute. | Use entre :min y :max elementos en :attribute. |
| between.file | Escolha para :attribute um arquivo entre :min e :max KB. | Choose a file for :attribute between :min and :max KB. | Elija para :attribute un archivo de entre :min y :max KB. |
| between.numeric | Informe em :attribute um valor entre :min e :max. | Enter a value between :min and :max in :attribute. | Introduzca en :attribute un valor entre :min y :max. |
| between.string | Use entre :min e :max caracteres em :attribute. | Use between :min and :max characters in :attribute. | Use entre :min y :max caracteres en :attribute. |
| min.array | Selecione pelo menos :min itens em :attribute. | Select at least :min items in :attribute. | Seleccione al menos :min elementos en :attribute. |
| min.file | Escolha para :attribute um arquivo com pelo menos :min KB. | Choose a file for :attribute of at least :min KB. | Elija para :attribute un archivo de al menos :min KB. |
| min.numeric | Informe em :attribute um valor igual ou maior que :min. | Enter a value of :min or more in :attribute. | Introduzca en :attribute un valor igual o mayor que :min. |
| min.string | Use pelo menos :min caracteres em :attribute. | Use at least :min characters in :attribute. | Use al menos :min caracteres en :attribute. |
| max.array | Selecione no máximo :max itens em :attribute. | Select at most :max items in :attribute. | Seleccione como máximo :max elementos en :attribute. |
| max.file | Escolha para :attribute um arquivo de até :max KB. | Choose a file for :attribute of at most :max KB. | Elija para :attribute un archivo de hasta :max KB. |
| max.numeric | Informe em :attribute um valor igual ou menor que :max. | Enter a value of :max or less in :attribute. | Introduzca en :attribute un valor igual o menor que :max. |
| max.string | Use no máximo :max caracteres em :attribute. | Use at most :max characters in :attribute. | Use como máximo :max caracteres en :attribute. |
| size.array | Selecione exatamente :size itens em :attribute. | Select exactly :size items in :attribute. | Seleccione exactamente :size elementos en :attribute. |
| size.file | Escolha para :attribute um arquivo com :size KB. | Choose a file for :attribute of :size KB. | Elija para :attribute un archivo de :size KB. |
| size.numeric | Informe :size em :attribute. | Enter :size in :attribute. | Introduzca :size en :attribute. |
| size.string | Use exatamente :size caracteres em :attribute. | Use exactly :size characters in :attribute. | Use exactamente :size caracteres en :attribute. |
| gt.array | A quantidade ou o valor de :attribute deve ser maior que :value itens. | The amount or value of :attribute must be greater than :value items. | La cantidad o el valor de :attribute debe ser mayor que :value elementos. |
| gt.file | A quantidade ou o valor de :attribute deve ser maior que :value KB. | The amount or value of :attribute must be greater than :value KB. | La cantidad o el valor de :attribute debe ser mayor que :value KB. |
| gt.numeric | A quantidade ou o valor de :attribute deve ser maior que :value. | The amount or value of :attribute must be greater than :value. | La cantidad o el valor de :attribute debe ser mayor que :value. |
| gt.string | A quantidade ou o valor de :attribute deve ser maior que :value caracteres. | The amount or value of :attribute must be greater than :value characters. | La cantidad o el valor de :attribute debe ser mayor que :value caracteres. |
| gte.array | A quantidade ou o valor de :attribute deve ser maior ou igual a :value itens. | The amount or value of :attribute must be greater than or equal to :value items. | La cantidad o el valor de :attribute debe ser mayor o igual a :value elementos. |
| gte.file | A quantidade ou o valor de :attribute deve ser maior ou igual a :value KB. | The amount or value of :attribute must be greater than or equal to :value KB. | La cantidad o el valor de :attribute debe ser mayor o igual a :value KB. |
| gte.numeric | A quantidade ou o valor de :attribute deve ser maior ou igual a :value. | The amount or value of :attribute must be greater than or equal to :value. | La cantidad o el valor de :attribute debe ser mayor o igual a :value. |
| gte.string | A quantidade ou o valor de :attribute deve ser maior ou igual a :value caracteres. | The amount or value of :attribute must be greater than or equal to :value characters. | La cantidad o el valor de :attribute debe ser mayor o igual a :value caracteres. |
| lt.array | A quantidade ou o valor de :attribute deve ser menor que :value itens. | The amount or value of :attribute must be less than :value items. | La cantidad o el valor de :attribute debe ser menor que :value elementos. |
| lt.file | A quantidade ou o valor de :attribute deve ser menor que :value KB. | The amount or value of :attribute must be less than :value KB. | La cantidad o el valor de :attribute debe ser menor que :value KB. |
| lt.numeric | A quantidade ou o valor de :attribute deve ser menor que :value. | The amount or value of :attribute must be less than :value. | La cantidad o el valor de :attribute debe ser menor que :value. |
| lt.string | A quantidade ou o valor de :attribute deve ser menor que :value caracteres. | The amount or value of :attribute must be less than :value characters. | La cantidad o el valor de :attribute debe ser menor que :value caracteres. |
| lte.array | A quantidade ou o valor de :attribute deve ser menor ou igual a :value itens. | The amount or value of :attribute must be less than or equal to :value items. | La cantidad o el valor de :attribute debe ser menor o igual a :value elementos. |
| lte.file | A quantidade ou o valor de :attribute deve ser menor ou igual a :value KB. | The amount or value of :attribute must be less than or equal to :value KB. | La cantidad o el valor de :attribute debe ser menor o igual a :value KB. |
| lte.numeric | A quantidade ou o valor de :attribute deve ser menor ou igual a :value. | The amount or value of :attribute must be less than or equal to :value. | La cantidad o el valor de :attribute debe ser menor o igual a :value. |
| lte.string | A quantidade ou o valor de :attribute deve ser menor ou igual a :value caracteres. | The amount or value of :attribute must be less than or equal to :value characters. | La cantidad o el valor de :attribute debe ser menor o igual a :value caracteres. |
| password.letters | Inclua pelo menos uma letra em :attribute. | Include at least one letter in :attribute. | Incluya al menos una letra en :attribute. |
| password.mixed | Inclua letras maiúsculas e minúsculas em :attribute. | Include uppercase and lowercase letters in :attribute. | Incluya letras mayúsculas y minúsculas en :attribute. |
| password.numbers | Inclua pelo menos um número em :attribute. | Include at least one number in :attribute. | Incluya al menos un número en :attribute. |
| password.symbols | Inclua pelo menos um símbolo em :attribute. | Include at least one symbol in :attribute. | Incluya al menos un símbolo en :attribute. |
| password.uncompromised | Essa senha apareceu em um vazamento de dados. Escolha outra senha. | This password appeared in a data breach. Choose another password. | Esta contraseña apareció en una filtración de datos. Elija otra contraseña. |

## Nomes dos campos

| Campo da API | Português | English | Español |
| --- | --- | --- | --- |
| aad_client_id | identificador do aplicativo Microsoft | Microsoft application ID | identificador de la aplicación Microsoft |
| aad_client_secret | segredo do aplicativo Microsoft | Microsoft application secret | secreto de la aplicación Microsoft |
| aad_tenant_id | identificador do diretório Microsoft | Microsoft directory ID | identificador del directorio Microsoft |
| access_mode | modo de acesso | access mode | modo de acceso |
| account_status | situação da conta | account status | estado de la cuenta |
| analyst_id | analista | analyst | analista |
| attachments | anexos | attachments | adjuntos |
| attachments.* | anexo | attachment | adjunto |
| attachment_descriptions | descrições dos anexos | attachment descriptions | descripciones de los adjuntos |
| attachment_descriptions.* | descrição do anexo | attachment description | descripción del adjunto |
| audit_retention_months | retenção do histórico em meses | history retention in months | retención del historial en meses |
| can_create_projects | permissão para criar projetos | permission to create projects | permiso para crear proyectos |
| color | cor | color | color |
| decision | decisão de aprovação | approval decision | decisión de aprobación |
| delivery | forma de envio | delivery method | forma de envío |
| digest.frequency | frequência do resumo | digest frequency | frecuencia del resumen |
| digest.time | horário do resumo | digest time | hora del resumen |
| duration | duração | duration | duración |
| effective_from | início da vigência | valid from date | inicio de vigencia |
| effective_until | fim da vigência | valid until date | fin de vigencia |
| start | início | start | inicio |
| end | término | end | fin |
| events | eventos de notificação | notification events | eventos de notificación |
| events.*.email | notificação por e-mail | email notification | notificación por correo |
| events.*.in_app | notificação no aplicativo | in-app notification | notificación en la aplicación |
| expense_date | data da despesa | expense date | fecha del gasto |
| expires_in_hours | validade em horas | validity in hours | validez en horas |
| filter | filtro | filter | filtro |
| from | data inicial | start date | fecha inicial |
| to | data final | end date | fecha final |
| google_email | e-mail da conta Google | Google account email | correo de la cuenta Google |
| id | identificador | identifier | identificador |
| location | local | location | ubicación |
| meeting_url | link da reunião | meeting link | enlace de la reunión |
| members.*.role | função do integrante | member role | función del integrante |
| members.*.user_id | integrante da equipe | team member | integrante del equipo |
| message | mensagem | message | mensaje |
| microsoft_email | e-mail da conta Microsoft | Microsoft account email | correo de la cuenta Microsoft |
| note | observação | note | nota |
| notify_new_login | aviso de novo acesso | new login notification | aviso de nuevo acceso |
| notify_password_change | aviso de alteração da senha | password change notification | aviso de cambio de contraseña |
| notify_provider_link | aviso de vínculo de conta externa | external account link notification | aviso de vinculación de cuenta externa |
| organization_name | nome da organização | organization name | nombre de la organización |
| participant_ids | participantes | participants | participantes |
| participant_ids.* | participante | participant | participante |
| permissions | permissões | permissions | permisos |
| permissions.* | permissão | permission | permiso |
| permissions.*.allowed | autorização da permissão | permission authorization | autorización del permiso |
| permissions.*.id | permissão | permission | permiso |
| position | posição na lista | list position | posición en la lista |
| profile | perfil de acesso | access profile | perfil de acceso |
| project_role | função no projeto | project role | función en el proyecto |
| provider | provedor de integração | integration provider | proveedor de integración |
| providers | provedores de integração | integration providers | proveedores de integración |
| providers.* | provedor de integração | integration provider | proveedor de integración |
| quiet_hours.enabled | ativação do horário de silêncio | quiet hours activation | activación del horario de silencio |
| quiet_hours.end | fim do horário de silêncio | quiet hours end | fin del horario de silencio |
| quiet_hours.start | início do horário de silêncio | quiet hours start | inicio del horario de silencio |
| receipt_path | comprovante da despesa | expense receipt | comprobante del gasto |
| relationship_type | tipo de vínculo | relationship type | tipo de vínculo |
| request_key | identificador da solicitação | request identifier | identificador de la solicitud |
| requires_password_creation | exigência de criação de senha | password creation requirement | requisito de creación de contraseña |
| reviewer_id | responsável pela aprovação | approver | responsable de aprobación |
| search | termo de busca | search term | término de búsqueda |
| sku | código do produto | product code | código del producto |
| target | destino | target | destino |
| teams_event_id | evento do Teams | Teams event | evento de Teams |
| two_factor_code | código de autenticação em duas etapas | two-factor authentication code | código de autenticación en dos pasos |
| type | tipo | type | tipo |
| version | versão do registro | record version | versión del registro |
| name | nome | name | nombre |
| first_name | nome | first name | nombre |
| last_name | sobrenome | last name | apellido |
| email | e-mail | email | correo electrónico |
| password | senha | password | contraseña |
| password_confirmation | confirmação da senha | password confirmation | confirmación de contraseña |
| current_password | senha atual | current password | contraseña actual |
| proposal_number | número da proposta | proposal number | número de propuesta |
| project_id | projeto | project | proyecto |
| project_manager_id | gerente do projeto | project manager | gerente del proyecto |
| account_manager_id | gerente de contas | account manager | gerente de cuentas |
| project_status_id | status do projeto | project status | estado del proyecto |
| project_situation_id | situação do projeto | project condition | situación del proyecto |
| client_id | cliente | client | cliente |
| proposal_date | data da proposta | proposal date | fecha de propuesta |
| start_date | data de início | start date | fecha de inicio |
| end_date | data de término | end date | fecha de finalización |
| billing_date | data de faturamento | billing date | fecha de facturación |
| due_date | prazo | due date | plazo |
| contract_value | valor do projeto | project value | valor del proyecto |
| commission_rate | comissão | commission | comisión |
| commission_basis | base da comissão | commission basis | base de comisión |
| estimated_labor_cost | custo estimado do analista | estimated analyst cost | costo estimado del analista |
| estimated_additional_cost | custo adicional estimado | estimated additional cost | costo adicional estimado |
| estimated_minutes | tempo estimado em minutos | estimated time in minutes | tiempo estimado en minutos |
| currency | moeda | currency | moneda |
| participation_type | tipo de participação | participation type | tipo de participación |
| external_project_name | nome do projeto externo | external project name | nombre del proyecto externo |
| external_project_code | código do projeto externo | external project code | código del proyecto externo |
| cpt_scope | escopo | scope | alcance |
| client_name | nome do cliente | client name | nombre del cliente |
| member_ids | equipe | team | equipo |
| member_ids.* | integrante da equipe | team member | integrante del equipo |
| members | integrantes da equipe | team members | integrantes del equipo |
| user_id | usuário | user | usuario |
| role | função | role | función |
| profile_id | perfil de acesso | access profile | perfil de acceso |
| organization_id | organização | organization | organización |
| company_id | empresa | company | empresa |
| status | status | status | estado |
| progress | progresso | progress | progreso |
| priority | prioridade | priority | prioridad |
| title | título | title | título |
| description | descrição | description | descripción |
| reason | justificativa | reason | justificación |
| file | arquivo | file | archivo |
| avatar | foto do perfil | profile photo | foto de perfil |
| timezone | fuso horário | time zone | zona horaria |
| locale | idioma | language | idioma |
| preferred_locale | idioma preferido | preferred language | idioma preferido |
| job_title | cargo | job title | cargo |
| department | departamento | department | departamento |
| phone | telefone | phone | teléfono |
| document | CNPJ | business registration number | identificación fiscal |
| legal_name | razão social | legal name | razón social |
| active | conta ou cadastro ativo | active account or record | cuenta o registro activo |
| worked_on | data da atividade | activity date | fecha de actividad |
| duration_minutes | duração em minutos | duration in minutes | duración en minutos |
| activity_type_id | tipo de atividade | activity type | tipo de actividad |
| is_overtime | indicação de hora extra | overtime indicator | indicación de horas extra |
| task_id | tarefa | task | tarea |
| amount | valor | amount | importe |
| expense_type_id | tipo de despesa | expense type | tipo de gasto |
| taxes | impostos | taxes | impuestos |
| taxes.*.tax_type_id | tipo do imposto | tax type | tipo de impuesto |
| taxes.*.rate | alíquota do imposto | tax rate | tasa del impuesto |
| taxes.*.fixed_amount | valor fixo do imposto | fixed tax amount | importe fijo del impuesto |
| taxes.*.calculation_type | forma de cálculo do imposto | tax calculation type | tipo de cálculo del impuesto |
| taxes.*.calculation_basis | base de cálculo do imposto | tax calculation basis | base de cálculo del impuesto |
| normal_cost_rate | custo por hora normal | normal hourly cost | costo por hora normal |
| overtime_cost_rate | custo por hora extra | overtime hourly cost | costo por hora extra |
| normal_sale_rate | valor de venda da hora normal | normal hourly sale rate | tarifa de venta por hora normal |
| overtime_sale_rate | valor de venda da hora extra | overtime hourly sale rate | tarifa de venta por hora extra |
| valid_from | início da vigência | valid from date | inicio de vigencia |
| valid_until | fim da vigência | valid until date | fin de vigencia |
| token | link ou código de acesso | access link or code | enlace o código de acceso |
| code | código de verificação | verification code | código de verificación |
| body | mensagem | message | mensaje |
| subject | assunto | subject | asunto |
| starts_at | início do evento | event start | inicio del evento |
| ends_at | fim do evento | event end | fin del evento |
| url | endereço | address | dirección |
| quantity | quantidade | quantity | cantidad |
| product_id | produto | product | producto |
| category_id | categoria | category | categoría |
| price | preço | price | precio |

## Autenticação e recuperação de senha

| Grupo/regra | Português | English | Español |
| --- | --- | --- | --- |
| auth.failed | E-mail ou senha incorretos. Confira os dados e tente novamente. | Incorrect email or password. Check your details and try again. | Correo o contraseña incorrectos. Revise los datos e inténtelo de nuevo. |
| auth.password | A senha está incorreta. Confira e tente novamente. | The password is incorrect. Check it and try again. | La contraseña es incorrecta. Revísela e inténtelo de nuevo. |
| auth.throttle | Muitas tentativas de acesso. Aguarde :seconds segundos e tente novamente. | Too many login attempts. Wait :seconds seconds and try again. | Demasiados intentos de acceso. Espere :seconds segundos e inténtelo de nuevo. |
| passwords.reset | Sua senha foi redefinida. Entre com a nova senha. | Your password has been reset. Sign in with the new password. | Su contraseña se ha restablecido. Inicie sesión con la nueva contraseña. |
| passwords.sent | Se houver uma conta elegível, enviaremos as instruções para redefinir a senha. | If an eligible account exists, we will send password reset instructions. | Si existe una cuenta elegible, enviaremos instrucciones para restablecer la contraseña. |
| passwords.throttled | Aguarde antes de solicitar outro link de redefinição de senha. | Wait before requesting another password reset link. | Espere antes de solicitar otro enlace para restablecer la contraseña. |
| passwords.token | Este link de redefinição de senha é inválido ou expirou. Solicite outro link. | This password reset link is invalid or expired. Request another link. | Este enlace para restablecer la contraseña no es válido o ha caducado. Solicite otro enlace. |
| passwords.user | Não foi possível redefinir a senha com esses dados. Confira o e-mail e solicite outro link. | Could not reset the password with these details. Check the email and request another link. | No se pudo restablecer la contraseña con estos datos. Revise el correo y solicite otro enlace. |

## HTTP, rede e interface

| Código ou origem | Título | Orientação em português |
| --- | --- | --- |
| 400 | Solicitação inválida | Não foi possível entender o pedido. Confira os dados informados e tente novamente. |
| 401 | Entre para continuar | Sua identificação não está disponível. Entre novamente para acessar esta área. |
| 402 | Operação indisponível | Esta operação exige uma condição de pagamento. Consulte a administração antes de continuar. |
| 403 | Você não tem acesso a esta área | Sua conta não tem a permissão necessária. Se precisar deste acesso, fale com a administração. |
| 404 | Página ou item não encontrado | O endereço pode ter mudado ou o item pode ter sido removido. Volte ao início para continuar. |
| 405 | Ação não permitida | Este endereço não aceita essa ação. Volte à tela anterior e use os controles do Spot. |
| 408 | O pedido demorou demais | A conexão foi interrompida antes de concluir o pedido. Confira sua rede e tente novamente. |
| 409 | Os dados foram alterados | Outra alteração pode ter ocorrido neste item. Confira os dados atuais antes de enviar novamente. |
| 410 | Este conteúdo não está mais disponível | O item ou link foi removido ou deixou de ser válido. Volte ao início para continuar. |
| 413 | Arquivo ou pedido muito grande | Reduza o tamanho dos arquivos ou envie menos itens de cada vez. |
| 415 | Formato não aceito | Confira os formatos aceitos nesta tela e escolha um arquivo compatível. |
| 419 | Sua sessão expirou | Por segurança, sua sessão precisa ser renovada. Entre novamente antes de repetir a ação. |
| 422 | Confira os dados informados | Há dados que precisam de correção. Revise os campos indicados e envie novamente. |
| 429 | Muitas tentativas em pouco tempo | Aguarde um pouco antes de tentar novamente. Isso ajuda a manter o serviço disponível. |
| 500 | Não foi possível concluir agora | O Spot encontrou uma falha interna. Tente novamente mais tarde. Se o problema continuar, informe a tela e o horário à administração. |
| 502 | Um serviço não respondeu como esperado | O Spot não conseguiu uma resposta válida de outro serviço. Tente novamente mais tarde. |
| 503 | Spot temporariamente indisponível | O serviço pode estar em manutenção ou com alta demanda. Aguarde um pouco e tente novamente. |
| 504 | Um serviço demorou para responder | O prazo de resposta foi excedido. Confira se a operação foi concluída antes de enviá-la novamente. |
| 4xx | Não foi possível atender ao pedido | Confira o endereço e os dados informados. Volte ao início se precisar de outro caminho. |
| 5xx | Serviço temporariamente indisponível | Não foi possível concluir o pedido agora. Tente novamente mais tarde. |
| network | Não foi possível conectar ao Spot | Confira sua conexão com a internet. Seus dados nesta tela foram mantidos; verifique o resultado antes de repetir uma operação. |
| application | Esta tela encontrou um problema | Não foi possível exibir esta parte do Spot. Recarregue a página para tentar novamente. Dados ainda não salvos podem precisar ser preenchidos outra vez. |
| google_configuration | O login com Google ainda não está configurado neste ambiente. | Use seu e-mail e senha para entrar. Para habilitar o Google, peça ao responsável pelo sistema para configurar a integração. |
| integration | Não foi possível conectar sua conta | A integração não foi concluída. Você pode entrar com e-mail e senha ou tentar conectar a conta novamente pelo Spot. |
| account | Conta não disponível para este acesso | Confira se está usando o e-mail cadastrado no Spot. Se precisar de ajuda com o acesso, fale com a administração. |

## Inventário dos textos específicos no código

Textos literais encontrados em contextos de erro, validação ou exceção. Inclui fragmentos de mensagens montadas com valores dinâmicos e expressões de cliente; não enumera cada resultado possível de interpolação nem mensagens de serviços externos. As referências apontam para o local da declaração. As mensagens genéricas da seção anterior cobrem falhas externas sem texto aproveitável.

| Origem | Texto ou fragmento |
| --- | --- |
| [app/Domain/Access/TeamsAccessWindow.php:22](../app/Domain/Access/TeamsAccessWindow.php#L22) | Selecione uma reunião ativa do Teams. |
| [app/Domain/Auth/MicrosoftToken.php:21](../app/Domain/Auth/MicrosoftToken.php#L21) | Reconecte sua conta Microsoft. |
| [app/Domain/Auth/MicrosoftToken.php:28](../app/Domain/Auth/MicrosoftToken.php#L28) | Reconecte sua conta Microsoft. |
| [app/Domain/Chat/ChatFormatting.php:125](../app/Domain/Chat/ChatFormatting.php#L125) | A formatação da mensagem é inválida ou excede o limite. Revise o texto e tente novamente. |
| [app/Domain/Expenses/Actions/ReviewProjectExpense.php:20](../app/Domain/Expenses/Actions/ReviewProjectExpense.php#L20) | Projeto finalizado não aceita alterações em despesas. |
| [app/Domain/Inventory/Actions/RegisterStockMovement.php:21](../app/Domain/Inventory/Actions/RegisterStockMovement.php#L21) | Estoque insuficiente para essa saída. |
| [app/Domain/Invitations/Actions/AcceptProjectInvitation.php:21](../app/Domain/Invitations/Actions/AcceptProjectInvitation.php#L21) | Convite inválido, expirado, revogado ou já utilizado. |
| [app/Domain/Invitations/Actions/AcceptProjectInvitation.php:26](../app/Domain/Invitations/Actions/AcceptProjectInvitation.php#L26) | Esta conta já existe. Entre com o mesmo e-mail antes de aceitar o convite. |
| [app/Domain/Projects/Actions/DeleteProject.php:18](../app/Domain/Projects/Actions/DeleteProject.php#L18) | Reabra o projeto antes de excluir. |
| [app/Domain/Projects/Actions/ReopenProject.php:20](../app/Domain/Projects/Actions/ReopenProject.php#L20) | O projeto não está finalizado. |
| [app/Domain/Projects/ProjectTeamManagers.php:15](../app/Domain/Projects/ProjectTeamManagers.php#L15) | Todo projeto deve ter pelo menos um gerente. Selecione o gerente do projeto. |
| [app/Domain/WorkLogs/Actions/CreateWorkLog.php:23](../app/Domain/WorkLogs/Actions/CreateWorkLog.php#L23) | Projeto finalizado não aceita apontamentos. |
| [app/Domain/WorkLogs/Actions/CreateWorkLog.php:28](../app/Domain/WorkLogs/Actions/CreateWorkLog.php#L28) | O analista não está atribuído ao projeto. |
| [app/Domain/WorkLogs/Actions/CreateWorkLog.php:37](../app/Domain/WorkLogs/Actions/CreateWorkLog.php#L37) | Não existe uma tarifa vigente para o analista nessa data. |
| [app/Http/Controllers/Api/AccountSecurityController.php:132](../app/Http/Controllers/Api/AccountSecurityController.php#L132) | Código de autenticação inválido. |
| [app/Http/Controllers/Api/AdminUserController.php:141](../app/Http/Controllers/Api/AdminUserController.php#L141) | Contas já ativadas não podem ser excluídas. Desative o acesso para preservar o histórico. |
| [app/Http/Controllers/Api/AdminUserController.php:149](../app/Http/Controllers/Api/AdminUserController.php#L149) | Contas já ativadas não podem ser excluídas. Desative o acesso para preservar o histórico. |
| [app/Http/Controllers/Api/AdminUserController.php:176](../app/Http/Controllers/Api/AdminUserController.php#L176) | Contas já ativadas não podem ser excluídas. Desative o acesso para preservar o histórico. |
| [app/Http/Controllers/Api/AdminUserController.php:99](../app/Http/Controllers/Api/AdminUserController.php#L99) | O último administrador ativo não pode perder o acesso administrativo. |
| [app/Http/Controllers/Api/CalendarController.php:115](../app/Http/Controllers/Api/CalendarController.php#L115) | O evento não possui identificação na agenda conectada. |
| [app/Http/Controllers/Api/CalendarController.php:129](../app/Http/Controllers/Api/CalendarController.php#L129) | Não foi possível excluir o evento na agenda de origem. Confira a conexão e a permissão e tente novamente. |
| [app/Http/Controllers/Api/CalendarController.php:151](../app/Http/Controllers/Api/CalendarController.php#L151) | O evento não possui identificação na agenda conectada. |
| [app/Http/Controllers/Api/CalendarController.php:161](../app/Http/Controllers/Api/CalendarController.php#L161) | O evento não possui identificação na agenda conectada. |
| [app/Http/Controllers/Api/CalendarController.php:57](../app/Http/Controllers/Api/CalendarController.php#L57) | Não foi possível sincronizar o Google Calendar. Confira a conexão em Apps. |
| [app/Http/Controllers/Api/CalendarController.php:69](../app/Http/Controllers/Api/CalendarController.php#L69) | Página Microsoft inválida. |
| [app/Http/Controllers/Api/CalendarController.php:86](../app/Http/Controllers/Api/CalendarController.php#L86) | Não foi possível sincronizar o Microsoft 365. Confira a conexão em Apps. |
| [app/Http/Controllers/Api/ChatController.php:122](../app/Http/Controllers/Api/ChatController.php#L122) | Não foi possível armazenar o anexo. |
| [app/Http/Controllers/Api/ChatController.php:178](../app/Http/Controllers/Api/ChatController.php#L178) | O acesso a esta conversa não está disponível. |
| [app/Http/Controllers/Api/ChatController.php:185](../app/Http/Controllers/Api/ChatController.php#L185) | Somente o autor pode editar a mensagem. |
| [app/Http/Controllers/Api/ChatController.php:194](../app/Http/Controllers/Api/ChatController.php#L194) | A mensagem foi alterada. Atualize a conversa antes de editar novamente. |
| [app/Http/Controllers/Api/ChatController.php:21](../app/Http/Controllers/Api/ChatController.php#L21) | Seu perfil não tem acesso ao chat. |
| [app/Http/Controllers/Api/ChatController.php:220](../app/Http/Controllers/Api/ChatController.php#L220) | Escreva uma mensagem antes de enviar. |
| [app/Http/Controllers/Api/ChatController.php:221](../app/Http/Controllers/Api/ChatController.php#L221) | Escreva uma mensagem ou selecione um anexo. |
| [app/Http/Controllers/Api/ChatController.php:222](../app/Http/Controllers/Api/ChatController.php#L222) | Informe descrições válidas para as imagens. |
| [app/Http/Controllers/Api/ChatController.php:223](../app/Http/Controllers/Api/ChatController.php#L223) | Informe até 3 descrições de imagem. |
| [app/Http/Controllers/Api/ChatController.php:224](../app/Http/Controllers/Api/ChatController.php#L224) | A descrição da imagem deve conter texto. |
| [app/Http/Controllers/Api/ChatController.php:235](../app/Http/Controllers/Api/ChatController.php#L235) | Selecione quem deve analisar sua solicitação. |
| [app/Http/Controllers/Api/ChatController.php:236](../app/Http/Controllers/Api/ChatController.php#L236) | Selecione um aprovador válido. |
| [app/Http/Controllers/Api/ChatController.php:237](../app/Http/Controllers/Api/ChatController.php#L237) | Explique por que precisa conversar com esta pessoa. |
| [app/Http/Controllers/Api/ChatController.php:238](../app/Http/Controllers/Api/ChatController.php#L238) | O motivo deve conter texto. |
| [app/Http/Controllers/Api/ChatController.php:240](../app/Http/Controllers/Api/ChatController.php#L240) | Atualize a conversa antes de editar novamente. |
| [app/Http/Controllers/Api/ChatController.php:241](../app/Http/Controllers/Api/ChatController.php#L241) | Atualize a conversa antes de editar novamente. |
| [app/Http/Controllers/Api/ChatController.php:242](../app/Http/Controllers/Api/ChatController.php#L242) | Atualize a conversa antes de editar novamente. |
| [app/Http/Controllers/Api/ChatController.php:266](../app/Http/Controllers/Api/ChatController.php#L266) | Não há administrador ou gerente administrador disponível. Peça à administração para verificar os perfis. |
| [app/Http/Controllers/Api/ChatController.php:269](../app/Http/Controllers/Api/ChatController.php#L269) | Selecione um administrador ou gerente administrador ativo desta empresa, diferente de você. |
| [app/Http/Controllers/Api/ChatController.php:289](../app/Http/Controllers/Api/ChatController.php#L289) | Um dos participantes não está disponível para chat. |
| [app/Http/Controllers/Api/ChatController.php:28](../app/Http/Controllers/Api/ChatController.php#L28) | Pessoa indisponível para chat. |
| [app/Http/Controllers/Api/ChatController.php:313](../app/Http/Controllers/Api/ChatController.php#L313) | Esta autorização não está ativa. |
| [app/Http/Controllers/Api/ChatController.php:316](../app/Http/Controllers/Api/ChatController.php#L316) | Autorização de chat revogada |
| [app/Http/Controllers/Api/ChatController.php:316](../app/Http/Controllers/Api/ChatController.php#L316) | Solicite nova autorização se precisar retomar a conversa. |
| [app/Http/Controllers/Api/ChatController.php:331](../app/Http/Controllers/Api/ChatController.php#L331) | Esta mensagem contém apenas anexos. Não há texto para traduzir. |
| [app/Http/Controllers/Api/ChatController.php:334](../app/Http/Controllers/Api/ChatController.php#L334) | A tradução de mensagens ainda não foi configurada. Procure a administração. |
| [app/Http/Controllers/Api/ChatController.php:344](../app/Http/Controllers/Api/ChatController.php#L344) | Não foi possível traduzir agora. Tente novamente. |
| [app/Http/Controllers/Api/ChatController.php:346](../app/Http/Controllers/Api/ChatController.php#L346) | Não foi possível traduzir agora. Tente novamente. |
| [app/Http/Controllers/Api/DirectoryController.php:24](../app/Http/Controllers/Api/DirectoryController.php#L24) | Não foi possível autenticar no diretório Microsoft. Verifique as credenciais e o consentimento. |
| [app/Http/Controllers/Api/DirectoryController.php:37](../app/Http/Controllers/Api/DirectoryController.php#L37) | Página inválida. |
| [app/Http/Controllers/Api/DirectoryController.php:43](../app/Http/Controllers/Api/DirectoryController.php#L43) | Não foi possível listar o diretório. Verifique User.Read.All e o consentimento administrativo. |
| [app/Http/Controllers/Api/DirectoryController.php:53](../app/Http/Controllers/Api/DirectoryController.php#L53) | Não foi possível consultar este usuário no diretório. |
| [app/Http/Controllers/Api/DirectoryController.php:56](../app/Http/Controllers/Api/DirectoryController.php#L56) | O usuário precisa estar ativo e possuir e-mail válido. |
| [app/Http/Controllers/Api/FirstAccessController.php:24](../app/Http/Controllers/Api/FirstAccessController.php#L24) | Link inválido, expirado ou já utilizado. |
| [app/Http/Controllers/Api/HomeDashboardController.php:102](../app/Http/Controllers/Api/HomeDashboardController.php#L102) | Projeto finalizado não aceita alterações em tarefas. |
| [app/Http/Controllers/Api/ManagementController.php:172](../app/Http/Controllers/Api/ManagementController.php#L172) | Projeto finalizado não pode ser alterado. |
| [app/Http/Controllers/Api/ManagementController.php:224](../app/Http/Controllers/Api/ManagementController.php#L224) | Analista não atribuído ao projeto. |
| [app/Http/Controllers/Api/ManagementController.php:75](../app/Http/Controllers/Api/ManagementController.php#L75) | Informe um CNPJ com 14 números. |
| [app/Http/Controllers/Api/ManagementController.php:77](../app/Http/Controllers/Api/ManagementController.php#L77) | Informe um e-mail corporativo válido. |
| [app/Http/Controllers/Api/NotificationPreferenceController.php:33](../app/Http/Controllers/Api/NotificationPreferenceController.php#L33) | O fim do horário de silêncio deve ser diferente do início. |
| [app/Http/Controllers/Api/NotificationPreferenceController.php:36](../app/Http/Controllers/Api/NotificationPreferenceController.php#L36) | Uma ou mais preferências não estão disponíveis para este perfil. |
| [app/Http/Controllers/Api/ProjectAccessController.php:104](../app/Http/Controllers/Api/ProjectAccessController.php#L104) | Acesso revogado |
| [app/Http/Controllers/Api/ProjectAccessController.php:65](../app/Http/Controllers/Api/ProjectAccessController.php#L65) | Não foi possível consultar as reuniões. Confira a conexão Microsoft em Apps. |
| [app/Http/Controllers/Api/ProjectAccessController.php:83](../app/Http/Controllers/Api/ProjectAccessController.php#L83) | Não foi possível confirmar a reunião no Teams. Confira a conexão Microsoft. |
| [app/Http/Controllers/Api/ProjectAccessController.php:85](../app/Http/Controllers/Api/ProjectAccessController.php#L85) | Escolha uma reunião que ainda não terminou. |
| [app/Http/Controllers/Api/ProjectConfigurationController.php:143](../app/Http/Controllers/Api/ProjectConfigurationController.php#L143) | Projeto finalizado não pode ser configurado. |
| [app/Http/Controllers/Api/ProjectConfigurationController.php:68](../app/Http/Controllers/Api/ProjectConfigurationController.php#L68) | Escolha um administrador global, gerente-administrador ou gerente ativo desta empresa. |
| [app/Http/Controllers/Api/ProjectConfigurationController.php:96](../app/Http/Controllers/Api/ProjectConfigurationController.php#L96) | O usuário precisa ser membro ativo do projeto. |
| [app/Http/Controllers/Api/ProjectController.php:101](../app/Http/Controllers/Api/ProjectController.php#L101) | Projeto finalizado não pode ser alterado. |
| [app/Http/Controllers/Api/ProjectController.php:98](../app/Http/Controllers/Api/ProjectController.php#L98) | Projeto finalizado não pode ser alterado. |
| [app/Http/Controllers/Api/ProjectDocumentController.php:109](../app/Http/Controllers/Api/ProjectDocumentController.php#L109) | Escolha uma tarefa à qual você tenha acesso. |
| [app/Http/Controllers/Api/ProjectDocumentController.php:112](../app/Http/Controllers/Api/ProjectDocumentController.php#L112) | A tarefa deve pertencer ao projeto selecionado. |
| [app/Http/Controllers/Api/ProjectDocumentController.php:116](../app/Http/Controllers/Api/ProjectDocumentController.php#L116) | Escolha um projeto ao qual você tenha acesso. |
| [app/Http/Controllers/Api/ProjectExpenseController.php:30](../app/Http/Controllers/Api/ProjectExpenseController.php#L30) | Projeto finalizado não aceita novas despesas. |
| [app/Http/Controllers/Api/ProjectExpenseController.php:42](../app/Http/Controllers/Api/ProjectExpenseController.php#L42) | Analista não atribuído ao projeto. |
| [app/Http/Controllers/Api/ProjectInvitationController.php:113](../app/Http/Controllers/Api/ProjectInvitationController.php#L113) | Convite já utilizado não pode ser revogado. |
| [app/Http/Controllers/Api/ProjectInvitationController.php:46](../app/Http/Controllers/Api/ProjectInvitationController.php#L46) | Cargo e departamento não se aplicam ao papel Convidado. |
| [app/Http/Controllers/Api/ProjectInvitationController.php:54](../app/Http/Controllers/Api/ProjectInvitationController.php#L54) | O vínculo selecionado não corresponde ao papel no projeto. |
| [app/Http/Controllers/Api/ProjectInvitationController.php:57](../app/Http/Controllers/Api/ProjectInvitationController.php#L57) | Convidados podem receber somente permissões de visualização. |
| [app/Http/Controllers/Api/ProjectInvitationController.php:60](../app/Http/Controllers/Api/ProjectInvitationController.php#L60) | Informe a empresa do analista externo. |
| [app/Http/Controllers/Api/ProjectInvitationController.php:84](../app/Http/Controllers/Api/ProjectInvitationController.php#L84) | Convite inválido, expirado, revogado ou já utilizado. |
| [app/Http/Controllers/Api/ProjectWorkflowController.php:52](../app/Http/Controllers/Api/ProjectWorkflowController.php#L52) | Informe uma justificativa para continuar. |
| [app/Http/Controllers/Api/ProjectWorkflowController.php:53](../app/Http/Controllers/Api/ProjectWorkflowController.php#L53) | A justificativa deve ter pelo menos 10 caracteres. Explique o motivo da alteração. |
| [app/Http/Controllers/Api/TaskV1Controller.php:144](../app/Http/Controllers/Api/TaskV1Controller.php#L144) | Projeto finalizado não aceita alterações em tarefas. |
| [app/Http/Controllers/Api/TaskV1Controller.php:182](../app/Http/Controllers/Api/TaskV1Controller.php#L182) | Analista inválido. |
| [app/Http/Controllers/Api/TaskV1Controller.php:184](../app/Http/Controllers/Api/TaskV1Controller.php#L184) | Analista não atribuído ao projeto. |
| [app/Http/Controllers/Api/TaskV1Controller.php:196](../app/Http/Controllers/Api/TaskV1Controller.php#L196) | Selecione coparticipantes ativos deste projeto. |
| [app/Http/Controllers/Api/TeamController.php:52](../app/Http/Controllers/Api/TeamController.php#L52) | Projeto finalizado não permite editar o time. |
| [app/Http/Controllers/Api/TeamController.php:62](../app/Http/Controllers/Api/TeamController.php#L62) | Todo projeto deve ter pelo menos um gerente. Selecione o gerente do projeto. |
| [app/Http/Controllers/Api/TeamController.php:65](../app/Http/Controllers/Api/TeamController.php#L65) | Escolha um administrador global, gerente-administrador ou gerente ativo desta empresa. |
| [app/Http/Requests/SaveProjectRequest.php:15](../app/Http/Requests/SaveProjectRequest.php#L15) | Projeto finalizado não pode ser alterado. |
| [app/Http/Requests/SaveProjectRequest.php:75](../app/Http/Requests/SaveProjectRequest.php#L75) | Todo projeto deve ter pelo menos um gerente. Selecione o gerente do projeto. |
| [app/Http/Requests/SaveProjectRequest.php:76](../app/Http/Requests/SaveProjectRequest.php#L76) | Selecione os usuários da equipe na lista. |
| [app/Http/Requests/SaveProjectRequest.php:77](../app/Http/Requests/SaveProjectRequest.php#L77) | Selecione até 500 usuários para a equipe. |
| [app/Http/Requests/SaveProjectRequest.php:79](../app/Http/Requests/SaveProjectRequest.php#L79) | Selecione um usuário válido para a equipe. |
| [app/Http/Requests/SaveProjectRequest.php:80](../app/Http/Requests/SaveProjectRequest.php#L80) | Selecione um usuário válido para a equipe. |
| [app/Http/Requests/SaveProjectRequest.php:81](../app/Http/Requests/SaveProjectRequest.php#L81) | Não repita o mesmo usuário na equipe. |
| [app/Http/Requests/SaveProjectRequest.php:82](../app/Http/Requests/SaveProjectRequest.php#L82) | Escolha usuários ativos e com conta ativada desta empresa. |
| [app/Http/Requests/SaveProjectRequest.php:94](../app/Http/Requests/SaveProjectRequest.php#L94) | Sem permissão para alterar:  |
| [app/Models/ChatAttachment.php:16](../app/Models/ChatAttachment.php#L16) | Anexos de mensagens não podem ser excluídos. |
| [app/Models/ChatMessage.php:22](../app/Models/ChatMessage.php#L22) | Mensagens de chat não podem ser excluídas. |
| [resources/js/App.jsx:1007](../resources/js/App.jsx#L1007) | Não foi possível salvar. |
| [resources/js/App.jsx:1014](../resources/js/App.jsx#L1014) | Não foi possível restaurar os padrões. |
| [resources/js/App.jsx:1058](../resources/js/App.jsx#L1058) | Não foi possível carregar os projetos atribuídos. |
| [resources/js/App.jsx:1071](../resources/js/App.jsx#L1071) | Não foi possível carregar os fusos horários. |
| [resources/js/App.jsx:1100](../resources/js/App.jsx#L1100) | Não foi possível salvar o perfil. |
| [resources/js/App.jsx:1132](../resources/js/App.jsx#L1132) | Não foi possível enviar a imagem. |
| [resources/js/App.jsx:1147](../resources/js/App.jsx#L1147) | Não foi possível buscar a foto da Microsoft agora. Tente novamente. |
| [resources/js/App.jsx:1211](../resources/js/App.jsx#L1211) | Não foi possível restaurar a imagem padrão. |
| [resources/js/App.jsx:1371](../resources/js/App.jsx#L1371) | Não foi possível carregar os usuários. |
| [resources/js/App.jsx:1389](../resources/js/App.jsx#L1389) | Não foi possível cadastrar o usuário. |
| [resources/js/App.jsx:1410](../resources/js/App.jsx#L1410) | Não foi possível atualizar o acesso. |
| [resources/js/App.jsx:1429](../resources/js/App.jsx#L1429) | Não foi possível excluir o cadastro. |
| [resources/js/App.jsx:1536](../resources/js/App.jsx#L1536) | Não foi possível carregar o painel. |
| [resources/js/App.jsx:1551](../resources/js/App.jsx#L1551) | Não foi possível atualizar a tarefa. |
| [resources/js/App.jsx:1576](../resources/js/App.jsx#L1576) | home-state error |
| [resources/js/App.jsx:1608](../resources/js/App.jsx#L1608) | Não foi possível carregar as equipes. |
| [resources/js/App.jsx:1622](../resources/js/App.jsx#L1622) | Não foi possível carregar o time. |
| [resources/js/App.jsx:1654](../resources/js/App.jsx#L1654) | Não foi possível salvar o time. |
| [resources/js/App.jsx:1695](../resources/js/App.jsx#L1695) | Não foi possível carregar os clientes. |
| [resources/js/App.jsx:1714](../resources/js/App.jsx#L1714) | Não foi possível salvar o cliente. |
| [resources/js/App.jsx:1722](../resources/js/App.jsx#L1722) | Não foi possível arquivar o cliente. |
| [resources/js/App.jsx:1778](../resources/js/App.jsx#L1778) | Não foi possível carregar o histórico. |
| [resources/js/App.jsx:2288](../resources/js/App.jsx#L2288) | As senhas não coincidem. Confira a confirmação da senha. |
| [resources/js/App.jsx:2301](../resources/js/App.jsx#L2301) | Não foi possível ativar a conta. |
| [resources/js/App.jsx:2304](../resources/js/App.jsx#L2304) | Não foi possível concluir. Tente novamente. |
| [resources/js/App.jsx:2348](../resources/js/App.jsx#L2348) | Este convite não está mais disponível. |
| [resources/js/App.jsx:2366](../resources/js/App.jsx#L2366) | Este convite não pôde ser aceito. |
| [resources/js/App.jsx:2409](../resources/js/App.jsx#L2409) | Não foi possível entrar. |
| [resources/js/App.jsx:2577](../resources/js/App.jsx#L2577) | Não foi possível enviar o link. |
| [resources/js/App.jsx:2616](../resources/js/App.jsx#L2616) | Não foi possível redefinir a senha. Tente novamente em instantes. |
| [resources/js/App.jsx:2618](../resources/js/App.jsx#L2618) | Não foi possível redefinir a senha. |
| [resources/js/App.jsx:303](../resources/js/App.jsx#L303) | Não foi possível carregar a caixa de entrada. |
| [resources/js/App.jsx:322](../resources/js/App.jsx#L322) | Não foi possível marcar como lida. |
| [resources/js/App.jsx:327](../resources/js/App.jsx#L327) | Não foi possível marcar todas como lidas. |
| [resources/js/App.jsx:332](../resources/js/App.jsx#L332) | Não foi possível arquivar a mensagem. |
| [resources/js/App.jsx:339](../resources/js/App.jsx#L339) | Não foi possível limpar a caixa de entrada. |
| [resources/js/App.jsx:346](../resources/js/App.jsx#L346) | Não foi possível restaurar a mensagem. |
| [resources/js/App.jsx:388](../resources/js/App.jsx#L388) | Não foi possível excluir o evento. Tente novamente. |
| [resources/js/App.jsx:398](../resources/js/App.jsx#L398) | Não foi possível confirmar a exclusão. Confira a agenda e tente novamente. |
| [resources/js/App.jsx:444](../resources/js/App.jsx#L444) | Não foi possível carregar a agenda. |
| [resources/js/App.jsx:459](../resources/js/App.jsx#L459) | Selecione pelo menos uma agenda. |
| [resources/js/App.jsx:460](../resources/js/App.jsx#L460) | Selecione pelo menos uma agenda. |
| [resources/js/App.jsx:474](../resources/js/App.jsx#L474) | Não foi possível criar o evento. |
| [resources/js/App.jsx:494](../resources/js/App.jsx#L494) | home-state error |
| [resources/js/App.jsx:600](../resources/js/App.jsx#L600) | Não foi possível carregar as integrações. |
| [resources/js/App.jsx:613](../resources/js/App.jsx#L613) | Não foi possível desconectar a integração. |
| [resources/js/App.jsx:697](../resources/js/App.jsx#L697) | A data final deve ser igual ou posterior à data inicial. |
| [resources/js/App.jsx:710](../resources/js/App.jsx#L710) | Não foi possível carregar os gráficos. |
| [resources/js/App.jsx:838](../resources/js/App.jsx#L838) | Não foi possível carregar os projetos. |
| [resources/js/App.jsx:876](../resources/js/App.jsx#L876) | Não foi possível criar o convite. |
| [resources/js/App.jsx:934](../resources/js/App.jsx#L934) | Não foi possível carregar a segurança da conta. |
| [resources/js/App.jsx:944](../resources/js/App.jsx#L944) | Não foi possível concluir a operação. |
| [resources/js/App.jsx:975](../resources/js/App.jsx#L975) | IP não identificado |
| [resources/js/App.jsx:977](../resources/js/App.jsx#L977) | IP não identificado |
| [resources/js/App.jsx:989](../resources/js/App.jsx#L989) | Não foi possível carregar as notificações. |
| [resources/js/AuditTrail.jsx:61](../resources/js/AuditTrail.jsx#L61) | Não foi possível carregar o histórico. |
| [resources/js/ChatPage.jsx:169](../resources/js/ChatPage.jsx#L169) | Cada arquivo deve ter no máximo 2 MB. |
| [resources/js/ChatPage.jsx:169](../resources/js/ChatPage.jsx#L169) | Escolha uma imagem JPG, PNG ou WebP. |
| [resources/js/ChatPage.jsx:178](../resources/js/ChatPage.jsx#L178) | Não foi possível baixar o anexo. Tente novamente. |
| [resources/js/ChatPage.jsx:17](../resources/js/ChatPage.jsx#L17) | Os anexos excedem o limite de envio. Anexe até 3 arquivos de 2 MB cada. |
| [resources/js/ChatPage.jsx:20](../resources/js/ChatPage.jsx#L20) | Não foi possível carregar o chat. Tente novamente. |
| [resources/js/DocumentsPage.jsx:27](../resources/js/DocumentsPage.jsx#L27) | Projeto indisponível |
| [resources/js/DocumentsPage.jsx:34](../resources/js/DocumentsPage.jsx#L34) | Não foi possível atualizar o vínculo. |
| [resources/js/DocumentsPage.jsx:65](../resources/js/DocumentsPage.jsx#L65) | Não foi possível carregar os documentos. |
| [resources/js/DocumentsPage.jsx:86](../resources/js/DocumentsPage.jsx#L86) | Não foi possível enviar o arquivo. |
| [resources/js/DocumentsPage.jsx:97](../resources/js/DocumentsPage.jsx#L97) | Não foi possível excluir o documento. |
| [resources/js/Management.jsx:107](../resources/js/Management.jsx#L107) | A justificativa deve ter pelo menos 10 caracteres. Explique o motivo da alteração. |
| [resources/js/Management.jsx:107](../resources/js/Management.jsx#L107) | Informe uma justificativa para continuar. |
| [resources/js/Management.jsx:110](../resources/js/Management.jsx#L110) | Confirmar exclusão? |
| [resources/js/Management.jsx:119](../resources/js/Management.jsx#L119) | Confirmar exclusão? |
| [resources/js/Management.jsx:13](../resources/js/Management.jsx#L13) | Não foi possível salvar. |
| [resources/js/Management.jsx:40](../resources/js/Management.jsx#L40) | Preencha o campo |
| [resources/js/Management.jsx:44](../resources/js/Management.jsx#L44) | Confira os campos indicados e corrija os dados antes de salvar. |
| [resources/js/Management.jsx:49](../resources/js/Management.jsx#L49) | Confira os campos indicados e corrija os dados antes de salvar. |
| [resources/js/ProjectAccess.jsx:15](../resources/js/ProjectAccess.jsx#L15) | Não foi possível concluir a operação. |
| [resources/js/ProjectResources.jsx:19](../resources/js/ProjectResources.jsx#L19) | Não foi possível carregar tarefas e documentos do projeto. |
| [resources/js/ProjectResources.jsx:19](../resources/js/ProjectResources.jsx#L19) | O projeto não está disponível para sua conta. |
| [resources/js/ProjectResources.jsx:22](../resources/js/ProjectResources.jsx#L22) | Não foi possível carregar tarefas e documentos do projeto. |
| [resources/js/TasksPage.jsx:101](../resources/js/TasksPage.jsx#L101) | Não foi possível carregar as tarefas. |
| [resources/js/TasksPage.jsx:117](../resources/js/TasksPage.jsx#L117) | Tarefa indisponível para edição. |
| [resources/js/TasksPage.jsx:139](../resources/js/TasksPage.jsx#L139) | Não foi possível salvar a tarefa. |
| [resources/js/TasksPage.jsx:153](../resources/js/TasksPage.jsx#L153) | Não foi possível excluir a tarefa. |
| [resources/js/TasksPage.jsx:57](../resources/js/TasksPage.jsx#L57) | Não foi possível mudar o status. Tente novamente. |
| [resources/js/TwoFactorSettings.jsx:37](../resources/js/TwoFactorSettings.jsx#L37) | Não foi possível carregar a segurança da conta. |
| [resources/js/TwoFactorSettings.jsx:40](../resources/js/TwoFactorSettings.jsx#L40) | Não foi possível concluir a operação. |
| [resources/js/api.js:86](../resources/js/api.js#L86) | Não foi possível concluir a operação. |

## Verificação e limites

Os resultados desta alteração estão em [verificação das mensagens de erro](verificacao-mensagens-de-erro.md). A cobertura automatizada não substitui revisão humana com teclado, leitor de tela, zoom e pessoas com deficiência. A associação visual e programática por campo foi ajustada no editor compartilhado de projetos e cadastros; outros formulários mantêm seus mecanismos existentes.
