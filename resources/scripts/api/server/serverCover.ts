import http from '@/api/http';

interface ServerCoverResponse {
    cover_image_url: string | null;
}

interface ServerIconResponse {
    icon_image_url: string | null;
}

export const uploadServerCover = async (uuid: string, image: File): Promise<string | null> => {
    const body = new FormData();
    body.append('cover', image);

    const { data } = await http.post<ServerCoverResponse>(`/api/client/servers/${uuid}/settings/cover`, body, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });

    return data.cover_image_url;
};

export const deleteServerCover = async (uuid: string): Promise<void> => {
    await http.delete(`/api/client/servers/${uuid}/settings/cover`);
};

export const uploadServerIcon = async (uuid: string, image: File): Promise<string | null> => {
    const body = new FormData();
    body.append('icon', image);

    const { data } = await http.post<ServerIconResponse>(`/api/client/servers/${uuid}/settings/icon`, body, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });

    return data.icon_image_url;
};

export const deleteServerIcon = async (uuid: string): Promise<void> => {
    await http.delete(`/api/client/servers/${uuid}/settings/icon`);
};
