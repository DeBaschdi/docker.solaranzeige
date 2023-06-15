;  In dieser Datei werden alle Befehle aufgeführt,
;  die für das jeweilige Gerät zugelassen sind.
;  Ist ein Befehl hier nicht aufgeführt, kann er
;  auch nicht an das Gerät gesendet werden. Die
;  Befehle sind nach Regler/Wechselrichter Typen
;  getrennt aufgeführt. Standardmäßig sind hier
;  nur wenige Befehle pro Gerät aufgelistet.
;  Sollen weitere Befehle benutzt werden, dann
;  hier wie vorgegeben eintragen. Aber Achtung!
;  Sie müssen sicher sein, dass der eingetragene
;  Befehl auch Ihr Gerät nicht beschädigt! Vor
;  allem in Kombination mit weiteren Befehlen.
;
;  Kommentare beginnen mit einem Semikolon
;  Nach einer Regler Überschrift die Auflistung
;  um eine Leerstelle einrücken.
;  # = Joker (Kann jede Zahl sein)


[Regler3]
  1 = LOAD_ON
  2 = LOAD_OFF

[Regler5]
  1 = B
  2 = B 2 ##.#
  3 = S
  4 = S ##.#
  5 = F
  6 = 9
  7 = L ###

[Regler7]
  1 = QPI
  2 = POP00
  3 = POP01
  4 = POP02

[Regler9]
  1 = EDA0
  2 = EDA1
  3 = EDB0
  4 = EDB1
  5 = EDC0
  6 = EDC1
  7 = EDD0
  8 = EDD1
  9 = EDE0
 10 = EDE1
 11 = EDF0
 12 = EDF1
 13 = EDG0
 14 = EDG1
 15 = EDH0
 16 = EDH1
 17 = SEP00
 18 = SEP01
 19 = SEP02
 20 = DM058
 21 = DM108


[Regler13]
  1 = RELAY_ON
  2 = RELAY_OFF
  3 = STOP_BALANCING
  4 = START_BALANCING

[Regler29]
  1 = amp_##
  2 = stp_#
  3 = alw_#
  4 = dwo_###

[Regler30]
  1 = ena_0
  2 = currtime_#####
  3 = curr_#####
  4 = setenergy_#######

[Regler33]
  1 = 10_#####
  2 = 11_#####
  3 = 12_#

[Regler35]
  1 = start_#
  2 = amp_#####
  3 = unlock_#

