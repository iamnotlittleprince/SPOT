// Return Portuguese interface text; the shared translator applies the active locale.
const labels = {
  planning: 'Planejamento',
  in_progress: 'Em andamento',
  review: 'Em revisão',
  completed: 'Concluído',
  done: 'Concluído',
  stopped: 'Parado',
  paused: 'Congelado',
  frozen: 'Congelado',
  cancelled: 'Cancelado',
  canceled: 'Cancelado',
};

export function projectStatusLabel(value) {
  if (!value) return 'Planejamento';
  return labels[String(value).trim().toLowerCase().replaceAll('-', '_')] || value;
}

export function projectStatus(project, statuses = []) {
  if (project.finalized_at) return 'Finalizado';
  const configured = statuses.find(status => String(status.id) === String(project.project_status_id));
  return projectStatusLabel(configured?.name || configured?.slug || project.status);
}
