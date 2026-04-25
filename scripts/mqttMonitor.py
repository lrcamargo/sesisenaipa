#!/usr/bin/env python3
"""
mqttMonitor.py
==============
Escuta todos os tópicos MQTT e mantém estado em JSON.

SEM acesso a banco de dados — zero credenciais aqui.
Toda leitura de banco (MAC→ambiente, crachá→nome) é feita pelo PHP.

Tópicos tratados:
  /{id}/status      → porta/dispositivo: online (JSON) ou "offline"
  /{id}/ar/status   → ar condicionado: online (JSON) ou "offline"
  /{id}/marca/get   → marca do AC (ex: hitachi)
  /{id}/entrada     → leitura de crachá (salva cracha + timestamp no JSON)

Estado salvo atomicamente em: /var/www/html/data/iot_estado.json
PID salvo em:                  /tmp/mqtt_monitor.pid
"""

import json, logging, os, re, signal, sys, time
from datetime import datetime

try:
    import paho.mqtt.client as mqtt
except ImportError:
    print("ERRO: pip install paho-mqtt --break-system-packages"); sys.exit(1)

# ── Configurações ─────────────────────────────────────────────────────────
BROKER      = "localhost"
PORT        = 1883
TOPIC       = "#"
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

# Estado em memória {chave: {...}}
# Porta: chave = topico_id         ex: "103"
# AC:    chave = topico_id + "_ar" ex: "203b_ar"
estado = {}

if os.path.exists(ESTADO_JSON):
    try:
        with open(ESTADO_JSON) as f:
            estado = json.load(f)
        log.info("Estado anterior carregado: %d entradas.", len(estado))
    except Exception:
        estado = {}


# ═══════════════════════════════════════════════════════════════════════════
# PERSISTÊNCIA
# ═══════════════════════════════════════════════════════════════════════════

def salvar_estado():
    """Escreve JSON de forma atômica (.tmp → rename)."""
    tmp = ESTADO_JSON + ".tmp"
    try:
        with open(tmp, "w", encoding="utf-8") as f:
            json.dump(estado, f, ensure_ascii=False, indent=2)
        os.replace(tmp, ESTADO_JSON)
    except Exception as e:
        log.error("Erro ao salvar JSON: %s", e)


# ═══════════════════════════════════════════════════════════════════════════
# PARSERS
# ═══════════════════════════════════════════════════════════════════════════

# /{id}/status, /{id}/ar/status, /{id}/marca/get, /{id}/entrada
TOPICO_RE = re.compile(r"^/?([^/]+)/([^/]+)(?:/([^/]+))?$")

def parse_topico(topico: str):
    m = TOPICO_RE.match(topico)
    return (m.group(1), m.group(2), m.group(3)) if m else (None, None, None)


def _base_disp(chave: str, topico_id: str, tipo: str) -> dict:
    if chave not in estado:
        estado[chave] = {"chave": chave, "topico_id": topico_id, "tipo": tipo}
    return estado[chave]


# ═══════════════════════════════════════════════════════════════════════════
# HANDLERS — sem banco, apenas JSON
# ═══════════════════════════════════════════════════════════════════════════

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
        disp.update({
            "status"      : "online",
            "device_name" : data.get("device"),
            "mac"         : data.get("mac"),
            "wifi"        : data.get("wifi"),
            "mqtt_status" : data.get("mqtt"),
            "rssi"        : data.get("rssi"),
            "ultima_vez"  : agora,
        })
        log.info("[%s/status] online mac=%s rssi=%s", topico_id, data.get("mac"), data.get("rssi"))
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
            disp.update({
                "status"      : "online",
                "device_name" : data.get("device"),
                "mac"         : data.get("mac") or disp.get("mac"),
                "wifi"        : data.get("wifi"),
                "mqtt_status" : data.get("mqtt"),
                "rssi"        : data.get("rssi"),
                "ultima_vez"  : agora,
            })
        except json.JSONDecodeError:
            if payload.strip().lower() == "online":
                disp.update({"status": "online", "ultima_vez": agora})
            else:
                log.warning("[%s/ar/status] payload inválido: %s", topico_id, payload[:80])
                return
        log.info("[%s/ar/status] online", topico_id)
    salvar_estado()


def handle_marca(topico_id: str, payload: str):
    chave = topico_id + "_ar"
    disp  = _base_disp(chave, topico_id, "ar_condicionado")
    marca = payload.strip()
    if marca:
        disp["marca"] = marca
        log.info("[%s/marca/get] %s", topico_id, marca)
        salvar_estado()


def handle_entrada(topico_id: str, payload: str):
    """
    Registra acesso no JSON — sem banco.
    O PHP lê o crachá do JSON e cruza com cadastroiot para exibir o nome.
    Mantém histórico dos últimos 100 acessos por porta no JSON.
    """
    cracha = payload.strip()
    if not cracha:
        return
    agora = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

    chave = topico_id
    disp  = _base_disp(chave, topico_id, "porta_ambiente")

    # Último acesso (para exibição no card)
    disp["ultimo_acesso"] = {
        "cracha"   : cracha,
        "data_hora": agora,
    }

    # Histórico de acessos da porta (últimos 100)
    historico = disp.get("historico_acessos", [])
    historico.insert(0, {"cracha": cracha, "data_hora": agora})
    disp["historico_acessos"] = historico[:100]

    log.info("[%s/entrada] crachá=%s", topico_id, cracha)
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
        topico  = msg.topic
        payload = msg.payload.decode("utf-8", errors="replace").strip()
        tid, sub1, sub2 = parse_topico(topico)
        if not tid:
            return

        if   sub1 in ("status", "estado") and sub2 is None:    handle_status_porta(tid, payload)
        elif sub1 == "ar" and sub2 in ("status", "estado"):       handle_status_ar(tid, payload)
        elif sub1 == "marca"   and sub2 == "get":                 handle_marca(tid, payload)
        elif sub1 == "entrada" and sub2 is None:                  handle_entrada(tid, payload)
        # demais tópicos ignorados silenciosamente

    except Exception as e:
        log.error("Erro ao processar [%s]: %s", msg.topic, e)


# ═══════════════════════════════════════════════════════════════════════════
# MAIN
# ═══════════════════════════════════════════════════════════════════════════

def signal_handler(sig, frame):
    log.info("Encerrando mqttMonitor...")
    try: client.disconnect()
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

log.info("Iniciando mqttMonitor (pid=%d) — sem acesso a banco.", os.getpid())

while True:
    try:
        client.connect(BROKER, PORT, keepalive=60)
        client.loop_forever()
    except Exception as e:
        log.error("Erro MQTT: %s — reconectando em 10s...", e)
        time.sleep(10)