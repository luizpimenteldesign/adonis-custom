import React from 'react';
import { Container, Box, Typography, Paper } from '@mui/material';
import { useParams } from 'react-router-dom';
import NavBar from '../components/NavBar';

function OSDetailPage() {
  const { id } = useParams();
  return (
    <>
      <NavBar />
      <Container maxWidth="lg">
        <Box sx={{ py: 4 }}>
          <Typography variant="h4" sx={{ mb: 4, fontWeight: 600 }}>Detalhes da OS #{id}</Typography>
          <Paper sx={{ p: 4 }}>
            <Typography variant="body1">Página em desenvolvimento...</Typography>
          </Paper>
        </Box>
      </Container>
    </>
  );
}

export default OSDetailPage;
