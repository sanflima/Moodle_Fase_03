<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

// ── Configuração MySQL (Hostinger) ────────────────────────────────────────────
    --green-light:#4caf50;--green-soft:#e7f3ea;--green-accent:#8bc34a;
define('DB_NAME', 'u999185157_Adm_2026');
define('DB_USER', 'u999185157_adm_if');
define('DB_PASS', 'Adm@ifba2026');

    --red:#c62828;--red-soft:#fdecea;────────────────────────────────────────────
function jsonErro($msg, $code=400) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error'=>$msg], JSON_UNESCAPED_UNICODE); exit;
}
function ok($data=[]) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE); exit;
}
function uid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff),mt_rand(0,0xffff),mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000,mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff),mt_rand(0,0xffff),mt_rand(0,0xffff));
}
function castUser(?array $u): ?array {
    if ($u) $u['primeiro_acesso'] = (bool)(int)$u['primeiro_acesso'];
    return $u ?: null;
}
function checkAdm(PDO $pdo): void {
    if (empty($_SESSION['user_id'])) jsonErro('Acesso negado: autenticação necessária.', 403);
    $s = $pdo->prepare("SELECT role FROM usuarios WHERE id=? LIMIT 1");
    $s->execute([$_SESSION['user_id']]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    if (!$row || !in_array('adm_senio', array_filter(array_map('trim', explode(',', $row['role'])))))
        jsonErro('Acesso negado: somente ADM_SENIO pode realizar esta operação.', 403);
}
function verifyPwd(string $plain, string $stored): bool {
    if (password_verify($plain, $stored)) return true;
    return $plain === $stored;
}

// ── Conexão MySQL ─────────────────────────────────────────────────────────────
try {
    $pdo = new PDO(
        'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ]
    );
} catch (Exception $e) { jsonErro('Banco indisponível: '.$e->getMessage(), 500); }

// ── Tabelas ───────────────────────────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
  id         VARCHAR(36)  PRIMARY KEY,
  nome       VARCHAR(255) NOT NULL,
  cpf        VARCHAR(14)  UNIQUE NOT NULL,
  siape      VARCHAR(20),
  role       VARCHAR(100) NOT NULL,
  email      VARCHAR(255) UNIQUE NOT NULL,
  turma      VARCHAR(150),
  matricula  VARCHAR(50),
  telefone   VARCHAR(20),
  senha      VARCHAR(255) NOT NULL DEFAULT '123@mudar',
  primeiro_acesso TINYINT(1) NOT NULL DEFAULT 1,
  data_criacao    DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ultimo_acesso   DATETIME  NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS reset_solicitacoes (
  id          VARCHAR(36) PRIMARY KEY,
  usuario_id  VARCHAR(36) NOT NULL,
  criado_em   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolvido   TINYINT(1)  NOT NULL DEFAULT 0,
  resolvido_em DATETIME   NULL,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS log_acessos (
  id         VARCHAR(36)  PRIMARY KEY,
  usuario_id VARCHAR(36)  NULL,
  acao       VARCHAR(100) NOT NULL,
    order: 1; DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS materiais (
  id              VARCHAR(36)  PRIMARY KEY,
  titulo          VARCHAR(255) NOT NULL,
  .dash-avatar{width:70px;height:70px;border-radius:50%;background:linear-gradient(135deg,var(--green-light),var(--green));color:#fff;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;cursor:pointer;position:relative;overflow:hidden;border:3px solid var(--border);transition:.2s;}
  disciplina      VARCHAR(150) NOT NULL,
  prof_id         VARCHAR(36)  NULL,
  .dash-welcome h2{font-size:26px;margin-bottom:4px;font-weight:700;}
  arquivo_original VARCHAR(255),
  .logout{background:#fde8e8;border:1.5px solid #f5b8b8;color:#c0392b;padding:10px 20px;border-radius:8px;cursor:pointer;font-size:15px;font-weight:700;font-family:inherit;transition:.2s;}
  criado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (prof_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS entregas (
  id              VARCHAR(36)  PRIMARY KEY,
  aluno_id        VARCHAR(36)  NOT NULL,
  disciplina      VARCHAR(150) NOT NULL,
  titulo          VARCHAR(255) NOT NULL,
  arquivo_nome    VARCHAR(100),
  arquivo_original VARCHAR(255),
  enviado_em      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  nota            VARCHAR(20),
  feedback        TEXT,
  FOREIGN KEY (aluno_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS eventos (
  id        VARCHAR(36)  PRIMARY KEY,
  titulo    VARCHAR(255) NOT NULL,
  descricao TEXT,
  data      DATE         NOT NULL,
  tipo      VARCHAR(30)  NOT NULL DEFAULT 'aviso',
  prof_id   VARCHAR(36)  NULL,
  criado_em DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (prof_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// ── Seed ──────────────────────────────────────────────────────────────────────
if ($pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn() == 0) {
    $seeds = [
        ['nome'=>'Prof. Ma. Sandro Ferreira de Lima',         'cpf'=>'54270111534','siape'=>'2082602','role'=>'adm_senio','email'=>'sandro.lima@ifbaiano.edu.br',          'turma'=>'Administração do Sistema','matricula'=>null,'telefone'=>null,'senha'=>'adm@ifba2026'],
        ['nome'=>'Prof. Ma. Cleisson Fabricio Leite Batista', 'cpf'=>'06935829445','siape'=>'2003972','role'=>'adm_senio','email'=>'cleisson.fabricio@ifbaiano.edu.br',    'turma'=>'Administração do Sistema','matricula'=>null,'telefone'=>null,'senha'=>'adm@ifba2026'],
        ['nome'=>'Prof. Dr. Carlos Menezes',                  'cpf'=>'11111111101','siape'=>'1000001','role'=>'prof',     'email'=>'carlos.menezes@ifbaiano.edu.br',        'turma'=>'Docente','matricula'=>null,'telefone'=>null,'senha'=>'123@mudar'],
        ['nome'=>'Profa. Ma. Ana Lúcia Ribeiro',              'cpf'=>'11111111102','siape'=>'1000002','role'=>'prof',     'email'=>'ana.ribeiro@ifbaiano.edu.br',           'turma'=>'Docente','matricula'=>null,'telefone'=>null,'senha'=>'123@mudar'],
        ['nome'=>'Rayssa Evangelista Conceição de Souza',     'cpf'=>'86304689551','siape'=>null,     'role'=>'aluno',   'email'=>'rayssa.souza@estudante.ifbaiano.edu.br','turma'=>'ADM-2026.1','matricula'=>'20261SBF01GB0040','telefone'=>'74991940899','senha'=>'123@mudar'],
        ['nome'=>'João Pedro Costa',                          'cpf'=>'23456789012','siape'=>null,     'role'=>'aluno',   'email'=>'joao.costa@estudante.ifbaiano.edu.br',   'turma'=>'ADM-2026.1 · 3º Período','matricula'=>null,'telefone'=>null,'senha'=>'123@mudar'],
    ];
    $s = $pdo->prepare("INSERT IGNORE INTO usuarios (id,nome,cpf,siape,role,email,turma,matricula,telefone,senha) VALUES (?,?,?,?,?,?,?,?,?,?)");
    foreach ($seeds as $u) {
        $s->execute([uid(),$u['nome'],$u['cpf'],$u['siape'],$u['role'],$u['email'],$u['turma'],$u['matricula'],$u['telefone'],password_hash($u['senha'], PASSWORD_DEFAULT)]);
    }
    $ev = $pdo->prepare("INSERT INTO eventos (id,titulo,descricao,data,tipo) VALUES (?,?,?,?,?)");
    $ev->execute([uid(),'Prova: Teoria Geral da Administração II','Conteúdo: caps. 1-8 do livro base.',date('Y-m-d',strtotime('+7 days')),'prova']);
    $ev->execute([uid(),'Entrega: Estudo de Caso — PMEs','Enviar via portal até meia-noite.',date('Y-m-d',strtotime('+3 days')),'entrega']);
    $ev->execute([uid(),'Semana de Provas','Todas as avaliações do semestre acontecem nesta semana.',date('Y-m-d',strtotime('+14 days')),'aviso']);
}

// ── Upload de arquivo ─────────────────────────────────────────────────────────
if (isset($_FILES['arquivo'])) {
    $tipo  = $_POST['tipo'] ?? 'material';
    $dir   = __DIR__.'/../uploads/'.($tipo === 'entrega' ? 'entregas' : 'materiais').'/';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $ext   = strtolower(pathinfo($_FILES['arquivo']['name'], PATHINFO_EXTENSION));
    $allow = ['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','zip','jpg','jpeg','png'];
    if (!in_array($ext, $allow)) jsonErro('Tipo de arquivo não permitido.');
    if ($_FILES['arquivo']['size'] > 20*1024*1024) jsonErro('Arquivo maior que 20 MB.');
    $fname = uid().'.'.$ext;
    if (!move_uploaded_file($_FILES['arquivo']['tmp_name'], $dir.$fname)) jsonErro('Falha ao salvar arquivo.');
    $id = uid();
    if ($tipo === 'entrega') {
        $pdo->prepare("INSERT INTO entregas (id,aluno_id,disciplina,titulo,arquivo_nome,arquivo_original) VALUES (?,?,?,?,?,?)")
            ->execute([$id,$_POST['aluno_id']??'',$_POST['disciplina']??'Geral',$_POST['titulo']??'Entrega',$fname,$_FILES['arquivo']['name']]);
    } else {
        $pdo->prepare("INSERT INTO materiais (id,titulo,descricao,disciplina,prof_id,arquivo_nome,arquivo_original) VALUES (?,?,?,?,?,?,?)")
            ->execute([$id,$_POST['titulo']??'Material',$_POST['descricao']??'',$_POST['disciplina']??'Geral',$_POST['prof_id']??null,$fname,$_FILES['arquivo']['name']]);
    }
    ok(['ok'=>true,'id'=>$id,'arquivo'=>$fname]);
}

// ── Roteador JSON ─────────────────────────────────────────────────────────────
$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';

switch ($action) {

    case 'ping': ok(['ok'=>true,'php'=>PHP_VERSION,'db'=>'mysql']);

    case 'find_user': {
        $val   = preg_replace('/\D/','',$input['identifier']??'');
        $siape = trim($input['identifier']??'');
        $s = $pdo->prepare("SELECT * FROM usuarios WHERE cpf=? OR (siape IS NOT NULL AND siape=?) LIMIT 1");
        $s->execute([$val,$siape]);
        $u = $s->fetch() ?: null;
        if ($u) unset($u['senha']);
        ok(castUser($u));
    }
    case 'find_by_id': {
        $s = $pdo->prepare("SELECT * FROM usuarios WHERE id=? LIMIT 1");
        $s->execute([$input['id']??'']);
        $u = $s->fetch() ?: null;
        if ($u) unset($u['senha']);
        ok(castUser($u));
    }
    case 'login': {
        $ident    = trim($input['identifier']??'');
        $identNum = preg_replace('/\D/','',$ident);
        $pwd      = $input['password']??'';
        $s = $pdo->prepare("SELECT * FROM usuarios WHERE cpf=? OR (siape IS NOT NULL AND siape=?) LIMIT 1");
        $s->execute([$identNum,$ident]);
        $user = $s->fetch();
        if (!$user) jsonErro('Identificador não encontrado. Verifique seu CPF ou procure a Secretaria.', 401);
        if (!verifyPwd($pwd, $user['senha'])) jsonErro('Senha incorreta. Use 123@mudar no primeiro acesso.', 401);
        if (!str_starts_with($user['senha'], '$2')) {
            try { $pdo->prepare("UPDATE usuarios SET senha=? WHERE id=?")->execute([password_hash($pwd, PASSWORD_DEFAULT), $user['id']]); } catch(Exception $e){}
        }
        $pdo->prepare("UPDATE usuarios SET ultimo_acesso=NOW() WHERE id=?")->execute([$user['id']]);
        try { $pdo->prepare("INSERT INTO log_acessos (id,usuario_id,acao) VALUES (?,?,?)")->execute([uid(),$user['id'],'login']); } catch(Exception $e){}
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        unset($user['senha']);
        $user['primeiro_acesso'] = (bool)(int)$user['primeiro_acesso'];
        ok($user);
    }
    case 'logout': {
        if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
        ok(['ok'=>true]);
    }
    case 'change_password': {
        $id     = $input['id']??'';
        $oldPwd = $input['old_password']??null;
        $newPwd = $input['new_password']??'';
        if (!$id) jsonErro('ID do usuário obrigatório.');
        $isAdm  = !empty($_SESSION['user_id']) && !empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'adm_senio';
        $isSelf = !empty($_SESSION['user_id']) && $_SESSION['user_id'] === $id;
        $s = $pdo->prepare("SELECT senha, primeiro_acesso FROM usuarios WHERE id=? LIMIT 1");
        $s->execute([$id]);
        $row = $s->fetch();
        if (!$row) jsonErro('Usuário não encontrado.', 404);
        $isPrimeiro = (bool)(int)$row['primeiro_acesso'];
        if (!$isAdm && !$isSelf) {
            if (!$isPrimeiro || $oldPwd === null || !verifyPwd($oldPwd, $row['senha']))
                jsonErro('Acesso negado.', 403);
        }
        if (!$isAdm && $isSelf && $oldPwd !== null && !verifyPwd($oldPwd, $row['senha']))
            jsonErro('Senha atual incorreta.', 400);
        if (strlen($newPwd) < 6) jsonErro('A nova senha deve ter pelo menos 6 caracteres.', 400);
        $pdo->prepare("UPDATE usuarios SET senha=?,primeiro_acesso=0 WHERE id=?")->execute([password_hash($newPwd, PASSWORD_DEFAULT),$id]);
        ok(['ok'=>true]);
    }
    case 'update_user': {
        $id=$input['id']??''; $ch=$input['changes']??[];
        $sets=[]; $vals=[];
        foreach($ch as $k=>$v){
            $sets[]="$k=?";
            $vals[] = $k==='senha' ? password_hash((string)$v, PASSWORD_DEFAULT) : $v;
        }
        $vals[]=$id;
        $pdo->prepare("UPDATE usuarios SET ".implode(',',$sets)." WHERE id=?")->execute($vals);
        ok(['ok'=>true]);
    <a href="forum-administracao.html">💬 Forum</a>
    case 'get_users': {
        $b=trim($input['busca']??'');
        if($b){$s=$pdo->prepare("SELECT * FROM usuarios WHERE nome LIKE ? OR cpf LIKE ? ORDER BY nome");$s->execute(["%$b%","%$b%"]);}
        else{$s=$pdo->query("SELECT * FROM usuarios ORDER BY nome");}
        ok(array_map('castUser',$s->fetchAll()));
    }
    case 'get_profs': {
        $s=$pdo->query("SELECT id,nome,email,siape,turma,role FROM usuarios WHERE role LIKE '%prof%' OR role LIKE '%adm_senio%' ORDER BY nome");
        ok($s->fetchAll());
    }
    case 'create_user': {
        checkAdm($pdo);
        $cpf     = preg_replace('/\D/', '', $input['cpf'] ?? '');
    <section class="chooser" id="chooser"> '');
        $newRole = trim($input['role'] ?? '');
        $byCpf   = $pdo->prepare("SELECT id, role FROM usuarios WHERE cpf=? LIMIT 1");
        $byCpf->execute([$cpf]);
        $existing = $byCpf->fetch();
        if ($existing) {
            $roles = array_filter(array_map('trim', explode(',', $existing['role'])));
            if (!in_array($newRole, $roles)) {
                $roles[] = $newRole;
                $pdo->prepare("UPDATE usuarios SET role=? WHERE id=?")->execute([implode(',', $roles), $existing['id']]);
            }
            ok(['ok'=>true,'id'=>$existing['id'],'merged'=>true]);
        }
        $byEmail = $pdo->prepare("SELECT id FROM usuarios WHERE email=? LIMIT 1");
        $byEmail->execute([$email]);
        if ($byEmail->fetch()) jsonErro('E-mail já cadastrado para outro usuário.', 409);
        $id = uid();
        $pdo->prepare("INSERT INTO usuarios (id,nome,cpf,siape,role,email,turma,senha,primeiro_acesso) VALUES (?,?,?,?,?,?,?,?,1)")
            ->execute([$id,$input['nome']??'',$cpf,$input['siape']??null,$newRole,$email,$input['turma']??null,password_hash($input['senha']??'123@mudar', PASSWORD_DEFAULT)]);
        ok(['ok'=>true,'id'=>$id]);
    }
    case 'delete_user': {
        checkAdm($pdo);
        $pdo->prepare("DELETE FROM usuarios WHERE id=?")->execute([$input['id']??'']);
        ok(['ok'=>true]);
    }

    // ── Materiais ─────────────────────────────────────────────────────────────
    case 'get_materiais': {
        $disc=trim($input['disciplina']??'');
        if($disc){$s=$pdo->prepare("SELECT m.*,u.nome as prof_nome FROM materiais m LEFT JOIN usuarios u ON m.prof_id=u.id WHERE m.disciplina=? ORDER BY m.criado_em DESC");$s->execute([$disc]);}
        else{$s=$pdo->query("SELECT m.*,u.nome as prof_nome FROM materiais m LEFT JOIN usuarios u ON m.prof_id=u.id ORDER BY m.criado_em DESC");}
        ok($s->fetchAll());
    }
    case 'delete_material': {
        $r=$pdo->prepare("SELECT arquivo_nome FROM materiais WHERE id=?");
        $r->execute([$input['id']??'']);
        $row=$r->fetch();
        if($row && $row['arquivo_nome']) @unlink(__DIR__.'/../uploads/materiais/'.$row['arquivo_nome']);
        $pdo->prepare("DELETE FROM materiais WHERE id=?")->execute([$input['id']??'']);
        ok(['ok'=>true]);
    }

    // ── Entregas ──────────────────────────────────────────────────────────────
    case 'get_entregas': {
        $aluno=trim($input['aluno_id']??'');
        $disc=trim($input['disciplina']??'');
        $where=[]; $vals=[];
        if($aluno){$where[]='e.aluno_id=?';$vals[]=$aluno;}
        if($disc){$where[]='e.disciplina=?';$vals[]=$disc;}
        $wh=$where?'WHERE '.implode(' AND ',$where):'';
        $s=$pdo->prepare("SELECT e.*,u.nome as aluno_nome FROM entregas e LEFT JOIN usuarios u ON e.aluno_id=u.id $wh ORDER BY e.enviado_em DESC");
        $s->execute($vals);
        ok($s->fetchAll());
    }
    case 'avaliar_entrega': {
        $pdo->prepare("UPDATE entregas SET nota=?,feedback=? WHERE id=?")
            ->execute([$input['nota']??null,$input['feedback']??null,$input['id']??'']);
        ok(['ok'=>true]);
    }
    case 'delete_entrega': {
        $r=$pdo->prepare("SELECT arquivo_nome FROM entregas WHERE id=?");
        $r->execute([$input['id']??'']);
        $row=$r->fetch();
        if($row && $row['arquivo_nome']) @unlink(__DIR__.'/../uploads/entregas/'.$row['arquivo_nome']);
        $pdo->prepare("DELETE FROM entregas WHERE id=?")->execute([$input['id']??'']);
        ok(['ok'=>true]);
    }

    // ── Eventos / Calendário ──────────────────────────────────────────────────
    case 'get_eventos': {
        $s=$pdo->query("SELECT ev.*,u.nome as prof_nome FROM eventos ev LEFT JOIN usuarios u ON ev.prof_id=u.id ORDER BY ev.data ASC");
        ok($s->fetchAll());
    }
    case 'criar_evento': {
        $id=uid();
        $pdo->prepare("INSERT INTO eventos (id,titulo,descricao,data,tipo,prof_id) VALUES (?,?,?,?,?,?)")
            ->execute([$id,$input['titulo']??'Evento',$input['descricao']??'',$input['data'],$input['tipo']??'aviso',$input['prof_id']??null]);
        ok(['ok'=>true,'id'=>$id]);
    }
    case 'delete_evento': {
        $pdo->prepare("DELETE FROM eventos WHERE id=?")->execute([$input['id']??'']);
        ok(['ok'=>true]);
    }

    // ── Reset / Log ───────────────────────────────────────────────────────────
    case 'create_reset': {
        $pdo->prepare("INSERT INTO reset_solicitacoes (id,usuario_id) VALUES (?,?)")->execute([uid(),$input['usuario_id']??'']);
        ok(['ok'=>true]);
    }
    case 'get_resets': {
        $s=$pdo->query("SELECT r.*,u.nome,u.cpf,u.role FROM reset_solicitacoes r JOIN usuarios u ON r.usuario_id=u.id WHERE r.resolvido=0 ORDER BY r.criado_em DESC");
        ok($s->fetchAll());
    }
    case 'resolve_reset': {
        checkAdm($pdo);
        $pdo->prepare("UPDATE usuarios SET senha=?,primeiro_acesso=1 WHERE id=?")->execute([password_hash('123@mudar', PASSWORD_DEFAULT),$input['usuario_id']??'']);
        $pdo->prepare("UPDATE reset_solicitacoes SET resolvido=1,resolvido_em=NOW() WHERE id=?")->execute([$input['reset_id']??'']);
        ok(['ok'=>true]);
    }
    case 'log': {
        try{$pdo->prepare("INSERT INTO log_acessos (id,usuario_id,acao) VALUES (?,?,?)")->execute([uid(),$input['usuario_id']??null,$input['acao']??'']);}catch(Exception $e){}
        ok(['ok'=>true]);
    }
    default: jsonErro("Ação desconhecida: $action");
}
  <div class="inst-logo">  <div id="docentesGrid">    grid.innerHTML = docentes.map((d,i) => {  <!-- hidden input for photo upload -->    : `<div class="side-sec"><h4>Menu do Aluno</h4>/* ── Opens or archive selector for qualquer profile ── */  // Show teacher-only buttons