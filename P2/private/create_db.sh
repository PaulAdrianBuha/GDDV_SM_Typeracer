#!/bin/bash
rm games.db
sqlite3 games.db < setup.sql
chmod 777 games.db
