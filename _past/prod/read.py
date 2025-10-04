import re
import os
import sys
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), '..')))

from utils import sheet, ELO
import pandas as pd


ws = sheet.get_sheet(0)
rows = ws.get_all_values()


date_pattern = re.compile(r"^\d{4}\.\d{2}\.\d{2}$")  
match_pattern = re.compile(r"^(\d+)경기$")        
parse_kda = lambda x: int(x.strip()) if x.strip().isdigit() else None


players = []
bans = []
current_date = None
match_id = 0
idx = 4

while idx < len(rows):
    row = rows[idx]

    if row and date_pattern.match(row[0]):  current_date = row[0]
    if len(row) > 1:    m = match_pattern.match(row[1])
    else:   m = None

    if m and current_date:
        daily_match_no = int(m.group(1))
        match_id += 1

        for j, pos in enumerate(['탑', '정글', '미드', '원딜', '서폿']):
            idx += 1
            row = rows[idx]

            players.append({
                'match_id': match_id,
                'date': current_date,
                'daily_match_no': int(m.group(1)),
                'is_win': True,
                'player': row[2].strip(),
                'position': pos,
                'champion': row[3].strip(),
                'kills': parse_kda(row[4]),
                'deaths': parse_kda(row[5]),
                'assists': parse_kda(row[6]),
            })

            players.append({
                'match_id': match_id,
                'date': current_date,
                'daily_match_no': int(m.group(1)),
                'is_win': False,
                'player': row[7].strip(),
                'position': pos,
                'champion': row[8].strip(),
                'kills': parse_kda(row[9]),
                'deaths': parse_kda(row[10]),
                'assists': parse_kda(row[11]),
            })

            bans.append({
                'match_id': match_id,
                'is_win': True,
                'ban_order': j,
                'champion': row[12].strip(),
            })

            bans.append({
                'match_id': match_id,
                'is_win': False,
                'ban_order': j,
                'champion': row[13].strip(),
            })

    idx += 1


df_players = pd.DataFrame(players)
df_bans = pd.DataFrame(bans)

# df_players.to_csv(os.path.join(os.path.dirname(__file__), 'players.csv'), index=False, encoding='utf-8-sig')
# df_bans.to_csv(os.path.join(os.path.dirname(__file__), 'bans.csv'), index=False, encoding='utf-8-sig')

match_counts = (
    df_players
    .groupby('player')['match_id']
    .nunique()
    .sort_values(ascending=False)
)

'''
print("Plot")

import matplotlib.pyplot as plt
import matplotlib.dates as mdates
from matplotlib import font_manager

cmap = plt.get_cmap('tab20')

font_manager.fontManager.addfont('/usr/share/fonts/truetype/nanum/NanumGothic.ttf')
plt.rcParams['font.family']       = 'NanumGothic'
plt.rcParams['axes.unicode_minus'] = False

plt.figure(figsize=(20, 20))

elo_history = ELO.compute_ELO(df_players, 'match_id', 'is_win', 'player', 'date')

eligible_players = match_counts[match_counts >= 10].index # elo_history['player'].unique()


for i, p in enumerate(eligible_players):
    color = cmap(i % cmap.N)
    sub = elo_history[elo_history['player'] == p]
    plt.plot(sub['match_id'], sub['rating'], color=color, label=p)


ticks, labels = [], []
last_month = None
for mid, d in zip(elo_history['match_id'], elo_history['date']):
    this_month = d          
    if this_month != last_month:
        ticks.append(mid)
        labels.append(d)           
        last_month = this_month

plt.xticks(ticks, labels, rotation=45)
plt.title('ELO Rating over Matches')
plt.xlabel('# Match (Date)')
plt.ylabel('ELO Rating')
plt.legend(ncol=2, fontsize=8)
plt.savefig(os.path.join(os.path.dirname(__file__), 'elo_history.png'))
#'''