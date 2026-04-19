#!/usr/bin/env python3
"""
mqttMonitor.py
==============
Escuta todos os tópicos MQTT e mantém estado em JSON.
Zero inserção em banco — só lê cadastroiot para enriquecer nomes de crachá.

Tópicos tratados:
  /{id}/status      → porta/dispositivo: online (JSON) ou "offline"
  /{id}/ar/status   → ar condicionado: online (JSON) ou "offline"
  /{id}/marca/get   → marca do AC (ex: hitachi)
  /{id}/entrada     → leitura de crachá (enriquece último acesso no JSON)

Identificação do ambiente:
  O MAC recebido no payload é cruzado com macPorta / macArCondicionado
  na tabela intranet.laboratorios. Se encontrado, exibe o nome do ambiente.
  Caso contrário, exibe apenas o topico_id.

Estado salvo atomicamente em: /var/www/html/data/iot_estado.json
PID salvo em:                  /tmp/mqtt_monitor.pid
"""

import json, logging, os, re, signal, sys, time
from datetime import datetime

try:
    import paho.mqtt.client as mqtt
except ImportError:
    print("ERRO: pip install paho-mqtt --break-system-packages"); sys.exit(1)

try:
    import mysql.connector
except ImportError:
    print("ERRO: pip install mysql-connector-python --break-system-packages"); sys.exit(1)

# ── Configurações ─────────────────────────────────────────────────────────
BROKER      = "localhost"
PORT        = 1883
TOPIC       = "#"

DB_HOST     = "localhost"
DB_USER     = "root"
DB_PASS     = "BdP@25!"
DB_INTRANET = "intranet"
DB_IOT      = "cadastroiot"

ESTADO_JSON = "/var/www/html/data/iot_estado.json"
PID_FILE    = "/tmp/mqtt_monitor.pid"
LOG_DIR     = "/var/www/html/logs"
# ─────────────────────────────────────────────────────────────────────────

os.makedirs(LOG_DIR, exist_ok=True)
os.makedirs(os.path.dirname(ESTADO_JSON), exist_ok=True)

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    datefmt="%d/%m/%Y %H:%M:%S",
)
log = logging.getLogger(__name__)

# Estado em memória: {chave: {...}}
# Chave para porta:   topico_id          ex: "103"
# Chave para AC:      topico_id + "_ar"  ex: "203b_ar"
estado = {}

if os.path.exists(ESTADO_JSON):
    try:
        with open(ESTADO_JSON) as f:
            estado = json.load(f)
        log.info("Estado anterior carregado: %d entradas.", len(estado))
    except Exception:
        estado = {}


# ═══════════════════════════════════════════════════════════════════════════
# BANCO — apenas leitura
# ═══════════════════════════════════════════════════════════════════════════

_conn_intranet = None
_conn_iot      = None

def get_conn(database: str):
    global _conn_intranet, _conn_iot
    ref = "_conn_intranet" if database == DB_INTRANET else "_conn_iot"
    conn = _conn_intranet if database == DB_INTRANET else _conn_iot
    try:
        if conn and conn.is_connected():
            return conn
    except Exception:
        pass
    conn = mysql.connector.connect(
        host=DB_HOST, user=DB_USER, password=DB_PASS,
        database=database, autocommit=True, connection_timeout=10
    )
    if database == DB_INTRANET:
        _conn_intranet = conn
    else:
        _conn_iot = conn
    return conn


# Cache de MACs → nome do ambiente (recarregado a cada 5 minutos)
_mac_cache      = {}  # {MAC_UPPER: {"nome": str, "tipo": "porta"|"ar"}}
_mac_cache_time = 0
MAC_CACHE_TTL   = 300  # segundos

def mac_para_ambiente(mac: str) -> dict | None:
    """Retorna {nome, tipo} do ambiente pelo MAC, ou None se não encontrado."""
    global _mac_cache, _mac_cache_time
    if not mac:
        return None
    mac = mac.upper().strip()
    agora = time.time()
    if agora - _mac_cache_time > MAC_CACHE_TTL:
        try:
            conn = get_conn(DB_INTRANET)
            cur  = conn.cursor(dictionary=True)
            cur.execute("""
                SELECT nome,
                       macPorta          AS mac_porta,
                       macArCondicionado AS mac_ar
                FROM laboratorios
                WHERE macPorta IS NOT NULL
                   OR macArCondicionado IS NOT NULL
            """)
            cache = {}
            for row in cur.fetchall():
                if row["mac_porta"]:
                    cache[row["mac_porta"].upper()] = {"nome": row["nome"], "tipo": "porta_ambiente"}
                if row["mac_ar"]:
                    cache[row["mac_ar"].upper()]    = {"nome": row["nome"], "tipo": "ar_condicionado"}
            _mac_cache      = cache
            _mac_cache_time = agora
            log.info("Cache de MACs atualizado: %d entradas.", len(cache))
        except Exception as e:
            log.warning("Erro ao carregar cache de MACs: %s", e)
    return _mac_cache.get(mac)


def nome_por_cracha(cracha: str) -> tuple[str, str]:
    """Retorna (nome, registro) do crachá ou ('Desconhecido', '')."""
    try:
        conn = get_conn(DB_IOT)
        cur  = conn.cursor(dictionary=True)
        cur.execute("SELECT nome, registro FROM cadastro WHERE cracha = %s LIMIT 1", (cracha,))
        row = cur.fetchone()
        if row:
            return row["nome"], row["registro"]
    except Exception as e:
        log.warning("Erro ao buscar crachá %s: %s", cracha, e)
    return "Desconhecido", ""


# ═══════════════════════════════════════════════════════════════════════════
# PERSISTÊNCIA DO JSON
# ═══════════════════════════════════════════════════════════════════════════

def salvar_estado():
    tmp = ESTADO_JSON + ".tmp"
    try:
        with open(tmp, "w", encoding="utf-8") as f:
            json.dump(estado, f, ensure_ascii=False, indent=2)
        os.replace(tmp, ESTADO_JSON)
    except Exception as e:
        log.error("Erro ao salvar JSON: %s", e)


# ═══════════════════════════════════════════════════════════════════════════
# PARSERS DE TÓPICO
# ═══════════════════════════════════════════════════════════════════════════

# /{id}/status        → ("103",  "status",   None)
# /{id}/ar/status     → ("203b", "ar",       "status")
# /{id}/marca/get     → ("203b", "marca",    "get")
# /{id}/entrada       → ("106",  "entrada",  None)
TOPICO_RE = re.compile(r"^/?([^/]+)/([^/]+)(?:/([^/]+))?$")

def parse_topico(topico: str):
    m = TOPICO_RE.match(topico)
    if not m:
        return None, None, None
    return m.group(1), m.group(2), m.group(3)   # id, sub1, sub2


# ═══════════════════════════════════════════════════════════════════════════
# HANDLERS
# ═══════════════════════════════════════════════════════════════════════════

def _base_disp(chave: str, topico_id: str, tipo: str) -> dict:
    """Retorna o dict do dispositivo, criando se não existir."""
    if chave not in estado:
        estado[chave] = {
            "chave"    : chave,
            "topico_id": topico_id,
            "tipo"     : tipo,
        }
    return estado[chave]


def handle_status_porta(topico_id: str, payload: str):
    chave = topico_id
    agora = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    disp  = _base_disp(chave, topico_id, "porta_ambiente")

    if payload.strip().lower() == "offline":
        disp.update({"status": "offline", "ultima_vez": agora})
        log.info("[%s/status] offline", topico_id)
    else:
        try:
            data = json.loads(payload)
        except json.JSONDecodeError:
            log.warning("[%s/status] payload inválido: %s", topico_id, payload[:80])
            return

        mac = data.get("mac", "")
        amb = mac_para_ambiente(mac)
        disp.update({
            "status"      : "online",
            "device_name" : data.get("device"),
            "mac"         : mac,
            "wifi"        : data.get("wifi"),
            "mqtt_status" : data.get("mqtt"),
            "rssi"        : data.get("rssi"),
            "ultima_vez"  : agora,
            "tipo"        : amb["tipo"] if amb else disp.get("tipo", "porta_ambiente"),
            "nomeAmbiente": amb["nome"] if amb else None,
        })
        log.info("[%s/status] online mac=%s rssi=%s ambiente=%s",
                 topico_id, mac, data.get("rssi"), amb["nome"] if amb else "—")

    salvar_estado()


def handle_status_ar(topico_id: str, payload: str):
    chave = topico_id + "_ar"
    agora = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    disp  = _base_disp(chave, topico_id, "ar_condicionado")

    if payload.strip().lower() == "offline":
        disp.update({"status": "offline", "ultima_vez": agora})
        log.info("[%s/ar/status] offline", topico_id)
    else:
        try:
            data = json.loads(payload)
        except json.JSONDecodeError:
            # Pode ser só "online" como string simples
            if payload.strip().lower() == "online":
                disp.update({"status": "online", "ultima_vez": agora})
                log.info("[%s/ar/status] online", topico_id)
                salvar_estado()
            else:
                log.warning("[%s/ar/status] payload inválido: %s", topico_id, payload[:80])
            return

        mac = data.get("mac", "")
        amb = mac_para_ambiente(mac) if mac else None
        disp.update({
            "status"      : "online",
            "device_name" : data.get("device"),
            "mac"         : mac or disp.get("mac"),
            "wifi"        : data.get("wifi"),
            "mqtt_status" : data.get("mqtt"),
            "rssi"        : data.get("rssi"),
            "ultima_vez"  : agora,
            "nomeAmbiente": amb["nome"] if amb else disp.get("nomeAmbiente"),
        })
        log.info("[%s/ar/status] online mac=%s", topico_id, mac)

    salvar_estado()


def handle_marca(topico_id: str, payload: str):
    """Armazena a marca do AC no estado."""
    chave = topico_id + "_ar"
    disp  = _base_disp(chave, topico_id, "ar_condicionado")
    marca = payload.strip()
    if marca:
        disp["marca"] = marca
        log.info("[%s/marca/get] %s", topico_id, marca)
        salvar_estado()


def handle_entrada(topico_id: str, payload: str):
    """Registra o último acesso no JSON (sem inserção em banco)."""
    cracha = payload.strip()
    if not cracha:
        return
    agora = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    nome, registro = nome_por_cracha(cracha)

    # Enriquece o estado da porta correspondente
    chave = topico_id
    disp  = _base_disp(chave, topico_id, "porta_ambiente")
    disp["ultimo_acesso"] = {
        "cracha"   : cracha,
        "nome"     : nome,
        "registro" : registro,
        "data_hora": agora,
    }
    log.info("[%s/entrada] crachá=%s nome=%s", topico_id, cracha, nome)
    salvar_estado()


# ═══════════════════════════════════════════════════════════════════════════
# MQTT CALLBACKS
# ═══════════════════════════════════════════════════════════════════════════

def on_connect(client, userdata, flags, rc):
    if rc == 0:
        log.info("Conectado ao broker MQTT %s:%d", BROKER, PORT)
        client.subscribe(TOPIC)
        log.info("Inscrito em: %s", TOPIC)
    else:
        log.error("Falha na conexão MQTT. Código: %d", rc)


def on_disconnect(client, userdata, rc):
    if rc != 0:
        log.warning("Desconectado (rc=%d). Reconectando...", rc)


def on_message(client, userdata, msg):
    try:
        topico   = msg.topic
        payload  = msg.payload.decode("utf-8", errors="replace").strip()
        tid, sub1, sub2 = parse_topico(topico)
        if not tid:
            return

        # /{id}/status
        if sub1 == "status" and sub2 is None:
            handle_status_porta(tid, payload)

        # /{id}/ar/status
        elif sub1 == "ar" and sub2 == "status":
            handle_status_ar(tid, payload)

        # /{id}/marca/get
        elif sub1 == "marca" and sub2 == "get":
            handle_marca(tid, payload)

        # /{id}/entrada
        elif sub1 == "entrada" and sub2 is None:
            handle_entrada(tid, payload)

        # outros tópicos ignorados silenciosamente

    except Exception as e:
        log.error("Erro ao processar [%s]: %s", msg.topic, e)


# ═══════════════════════════════════════════════════════════════════════════
# MAIN
# ═══════════════════════════════════════════════════════════════════════════

def signal_handler(sig, frame):
    log.info("Encerrando mqttMonitor...")
    try: client.disconnect()
    except Exception: pass
    for c in [_conn_intranet, _conn_iot]:
        try:
            if c: c.close()
        except Exception: pass
    if os.path.exists(PID_FILE):
        os.remove(PID_FILE)
    sys.exit(0)


with open(PID_FILE, "w") as f:
    f.write(str(os.getpid()))

signal.signal(signal.SIGINT,  signal_handler)
signal.signal(signal.SIGTERM, signal_handler)

client = mqtt.Client()
client.on_connect    = on_connect
client.on_disconnect = on_disconnect
client.on_message    = on_message

log.info("Iniciando mqttMonitor (pid=%d)...", os.getpid())

while True:
    try:
        client.connect(BROKER, PORT, keepalive=60)
        client.loop_forever()
    except Exception as e:
        log.error("Erro MQTT: %s — reconectando em 10s...", e)
        time.sleep(10)