import React from 'react';
import { Grid, Card, CardContent, Typography } from '@mui/material';
import { AssignmentLate, Assignment, CheckCircle, Schedule } from '@mui/icons-material';

function Dashboard({ stats }) {
  const cards = [
    { label: 'Aguardando Análise', value: stats?.aguardando || 0, icon: AssignmentLate, color: '#FF9900' },
    { label: 'Em Análise', value: stats?.em_analise || 0, icon: Schedule, color: '#2196F3' },
    { label: 'Orçadas', value: stats?.orcadas || 0, icon: Assignment, color: '#9C27B0' },
    { label: 'Aprovadas', value: stats?.aprovadas || 0, icon: CheckCircle, color: '#4CAF50' }
  ];

  return (
    <Grid container spacing={3}>
      {cards.map((card, index) => {
        const Icon = card.icon;
        return (
          <Grid item xs={12} sm={6} md={3} key={index}>
            <Card>
              <CardContent>
                <Icon sx={{ fontSize: 40, color: card.color, mb: 1 }} />
                <Typography variant="h4" sx={{ fontWeight: 600, mb: 0.5 }}>{card.value}</Typography>
                <Typography variant="body2" color="text.secondary">{card.label}</Typography>
              </CardContent>
            </Card>
          </Grid>
        );
      })}
    </Grid>
  );
}

export default Dashboard;
