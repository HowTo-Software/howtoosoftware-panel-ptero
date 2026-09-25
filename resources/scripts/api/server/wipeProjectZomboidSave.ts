import http from '@/api/http';

export default (uuid: string): Promise<{ targets: string[] }> =>
    http.post(`/api/client/servers/${uuid}/settings/project-zomboid/wipe`).then(({ data }) => data);
