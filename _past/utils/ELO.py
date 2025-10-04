import pandas as pd
import numpy as np

def compute_ELO(df, time_index, win_index, player_index, date_index=None, K=32):
    df_ = df.copy()

    all_players = df_[player_index].unique()
    ratings = {p: 1000 for p in all_players}
    win_streak  = {p: 0 for p in all_players}
    history = []

    match_order = sorted(df_[time_index].unique())
    for i, m in enumerate(match_order):
        grp = df_[df_[time_index] == m]
        match_date = grp[date_index].iloc[0] if date_index else None

        if i == 0:
            for p in all_players:
                history.append({
                    time_index: 0,
                    date_index: match_date,
                    player_index: p,
                    'rating': ratings[p],
                    'streak': win_streak[p]
                })

        winners = grp.loc[grp[win_index], player_index].unique()
        losers  = grp.loc[~grp[win_index], player_index].unique()

        for p in winners:   win_streak[p] = 1 if win_streak[p]<0 else win_streak[p]+1
        for p in losers:    win_streak[p] = -1 if win_streak[p]>0 else win_streak[p]-1

        avg_win  = np.mean([ratings[p] for p in winners])
        avg_lose = np.mean([ratings[p] for p in losers ])

        Q_win, Q_lose = 10**(avg_win/400), 10**(avg_lose/400)
        E_win = Q_win / (Q_win + Q_lose)

        for p in winners:   ratings[p] += int(K * (1 - E_win))
        for p in losers:    ratings[p] -= int(K * E_win)

        for p in all_players:
            history.append({
                time_index: m,
                date_index: match_date,
                player_index: p,
                'rating': ratings[p],
                'streak': win_streak[p]
            })

    return pd.DataFrame(history)
